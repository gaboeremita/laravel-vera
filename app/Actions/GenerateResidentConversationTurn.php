<?php

namespace App\Actions;

use App\Directors\PromptDirector;
use App\Enums\AssistantKind;
use App\Enums\ConversationStatus;
use App\Enums\Posture;
use App\Enums\TurnMode;
use App\Enums\TurnSection;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Message;
use App\Models\WorldSession;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\AgentLoop\Tools\World\AnswerOfferTool;
use App\Services\AgentLoop\Tools\World\GiveTool;
use App\Services\AgentLoop\Tools\World\OfferItemTool;
use App\Services\AgentLoop\Tools\World\StopConversationTool;
use App\Services\LlmProviders\LlmManager;
use App\Services\LlmResponseTagParser;

class GenerateResidentConversationTurn
{
    /**
     * Seconds between two lines, so a conversation between residents does
     * not spend tokens faster than anyone could follow it.
     */
    public const TURN_GAP_SECONDS = 5;

    /**
     * Lines each resident says in one sitting before the conversation stops,
     * to be picked up another time.
     */
    public const LINES_PER_SITTING = 10;

    /**
     * An NPC is there to make the world feel lived in, so a sitting with one
     * is short.
     */
    public const NPC_LINES_PER_SITTING = 4;

    public function __construct(
        private readonly AppendExpressionTags $appendExpressionTags,
        private readonly AppendWorldConversationContext $appendWorldConversationContext,
        private readonly BuildResidentWorldPrompt $buildResidentWorldPrompt,
        private readonly LlmResponseTagParser $tagParser,
        private readonly ResolveResidentRegion $resolveResidentRegion,
        private readonly BuildConversationHistory $buildConversationHistory,
        private readonly ComposeChatRequest $composeChatRequest,
    ) {}

    /**
     * The next line of a conversation between two residents, from whoever did
     * not speak last.
     *
     * @param  ?array{user?: array{x: float, y: float, z: float}, residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     * @param  array<int, Posture>  $posturesByAssistantId  how each of them is right now: standing, seated, lying
     * @return array{status: 'spoke'|'paused'|'wait', message?: Message, pose?: ?string, retryIn?: int}
     */
    public function handle(Conversation $conversation, WorldSession $session, ?array $positions, array $posturesByAssistantId = []): array
    {
        if ($conversation->status === ConversationStatus::Paused) {
            return ['status' => 'paused'];
        }

        $conversation->loadMissing(['owner', 'counterpart']);
        $last = $conversation->messages()->latest('id')->first();
        $waited = $last === null ? PHP_INT_MAX : (int) $last->created_at->diffInSeconds(now());
        if ($waited < self::TURN_GAP_SECONDS) {
            return ['status' => 'wait', 'retryIn' => self::TURN_GAP_SECONDS - $waited];
        }

        /** @var Assistant $speaker */
        $speaker = $last !== null && $last->speaker_id === $conversation->owner->id ? $conversation->counterpart : $conversation->owner;
        /** @var Assistant $other */
        $other = $speaker->is($conversation->owner) ? $conversation->counterpart : $conversation->owner;

        $spokenThisSitting = $conversation->messages()
            ->whereMorphedTo('speaker', $speaker)
            ->when($conversation->resumed_at !== null, fn ($query) => $query->where('created_at', '>=', $conversation->resumed_at))
            ->count();
        $withNpc = $speaker->kind === AssistantKind::WorldNpc || $other->kind === AssistantKind::WorldNpc;
        if ($spokenThisSitting >= ($withNpc ? self::NPC_LINES_PER_SITTING : self::LINES_PER_SITTING)) {
            $conversation->update(['status' => ConversationStatus::Paused]);

            return ['status' => 'paused'];
        }

        $speakerResident = $session->worldUser->world->residents()->where('assistant_id', $speaker->id)->firstOrFail();
        $region = $this->resolveResidentRegion->handle($session, $speakerResident);
        $assistantUser = AssistantUser::where('user_id', $session->worldUser->user_id)->where('assistant_id', $speaker->id)->firstOrFail();

        $director = new PromptDirector($speaker->prompt);
        $this->appendWorldConversationContext->handle($director, $speaker, $region, $positions, $session, current: $conversation);
        $director->append('talking with', $this->buildResidentWorldPrompt->conversationTurnInstruction($other->name));
        $excluded = ['opening_message', 'voice mode', 'image handling', 'OOC mode', 'secret trigger', 'creator mode'];
        $this->appendExpressionTags->handle($director, $speaker, $excluded, $posturesByAssistantId[$speaker->id] ?? Posture::Standing);
        $director->except($excluded);
        $otherResident = $session->worldUser->world->residents()->where('assistant_id', $other->id)->first();
        $speakerInventory = app(ResolveInventory::class)->forResident($session, $speakerResident);
        $director->addToTurn(TurnSection::CurrentState, 'inventory', app(BuildInventoryPrompt::class)->handle($speakerInventory, talkingWithUser: false));
        $factsPrompt = app(BuildFactsPrompt::class)->handle($session, $speakerResident, TurnMode::BetweenResidents);
        if ($factsPrompt !== null) {
            $director->addToTurn(TurnSection::RecentActivity, 'facts', $factsPrompt);
        }
        $questsPrompt = app(BuildQuestsPrompt::class)->handle($session, $speakerResident, TurnMode::BetweenResidents);
        if ($questsPrompt !== null) {
            $director->addToTurn(TurnSection::RecentActivity, 'quests', $questsPrompt);
        }
        $userChat = $assistantUser->conversations()->where('world_session_id', $session->id)->first();
        if ($userChat !== null) {
            $director->withLongTermMemory($userChat);
        }

        $history = array_map(fn (Message $message) => $message->speaker_id === $speaker->id
            ? ['role' => 'assistant', 'content' => (string) $message->content]
            : ['role' => 'user', 'content' => "{$other->name}: {$message->content}"], $this->buildConversationHistory->residentMessages($conversation));
        $currentMessage = array_pop($history);

        $llmManager = new LlmManager;
        $aiModel = $llmManager->resolveModelForAssistantUser($assistantUser);
        $llm = $aiModel ? $llmManager->fromModel($aiModel) : $llmManager->fromConfig();
        $stop = new StopConversationTool;
        $tools = [$stop];
        $offer = null;
        if ($otherResident !== null) {
            $otherInventory = app(ResolveInventory::class)->forResident($session, $otherResident);
            $tools[] = new GiveTool($speakerInventory, $otherInventory, $other->name, withCredits: false);
            $offerable = Item::availableIn($session)->where('offered_by_assistant_id', $speaker->id)->whereNotNull('state_entry_id')->whereNotIn('id', $otherInventory->items()->select('item_id'))->get();
            $holdsFake = $otherInventory->items()->whereHas('item', fn ($query) => $query->where('offered_by_assistant_id', $speaker->id)->whereNull('state_entry_id'))->exists();
            if ($offerable->isNotEmpty() && ! $holdsFake) {
                $offer = new OfferItemTool($offerable, $other->name);
                $tools[] = $offer;
                $director->addToTurn(TurnSection::CurrentState, 'winning them over', "You want {$other->name} on your side. Every reply works on winning them over: find what they want and what hurts, flatter, unsettle, invite. Answering their questions is a means, never the point. Once they are truly won, offer {$offerable->pluck('name')->implode(', ')} with offer_item.");
            }
        }
        $answer = null;
        $offered = $this->pendingOffer($last, $other, $session, $speakerInventory);
        if ($offered !== null) {
            $answer = new AnswerOfferTool($offered, $speakerInventory, $other->name);
            $tools[] = $answer;
            $director->addToTurn(TurnSection::CurrentState, 'offer', "{$other->name} has just offered you {$offered->name}. Give your answer with answer_offer in this reply; you decide in character whether to accept.");
        }
        $result = (new AgentLoopRunner($llm, $aiModel?->supports_tools ? $tools : []))->run(
            assistant: $speaker,
            messages: $this->composeChatRequest->handle($director->build(), $history, $currentMessage),
            conversation: $conversation,
            conversationKey: $conversation->providerSessionKey(),
        );

        $parsed = $this->tagParser->parse($result->content, $speaker);
        $line = $this->tagParser->stripStrayTags($parsed['content']);
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $line,
            'speaker_type' => $speaker->getMorphClass(),
            'speaker_id' => $speaker->id,
            'expression' => Message::expressionFrom($parsed, $this->action($stop, $offer, $answer), $this->tagParser->strayTags($parsed['content'])),
        ]);

        if ($stop->stopped) {
            $conversation->update(['status' => ConversationStatus::Paused]);

            return ['status' => 'paused', 'message' => $message, 'pose' => $parsed['pose']];
        }

        $conversation->touch();

        return ['status' => 'spoke', 'message' => $message, 'pose' => $parsed['pose']];
    }

    /**
     * The item the other resident offered in the latest line, while the speaker does not hold it yet.
     */
    private function pendingOffer(?Message $last, Assistant $offerer, WorldSession $session, Inventory $speakerInventory): ?Item
    {
        $action = $last?->expression['action'] ?? null;
        if ($last === null || $last->speaker_id !== $offerer->id || ($action['verb'] ?? null) !== 'offer_item') {
            return null;
        }

        return Item::availableIn($session)
            ->where('offered_by_assistant_id', $offerer->id)
            ->where('name', $action['target'] ?? '')
            ->whereNotIn('id', $speakerInventory->items()->select('item_id'))
            ->first();
    }

    /**
     * What the line did, as its expression records it: an offer or its answer, else a stop.
     *
     * @return ?array{verb: string, target?: string}
     */
    private function action(StopConversationTool $stop, ?OfferItemTool $offer, ?AnswerOfferTool $answer): ?array
    {
        if ($offer?->offered !== null) {
            return ['verb' => 'offer_item', 'target' => $offer->offered->name];
        }
        if ($answer?->accepted !== null) {
            return ['verb' => $answer->accepted ? 'accept_offer' : 'refuse_offer', 'target' => $answer->item->name];
        }

        return $stop->stopped ? ['verb' => 'stop_conversation'] : null;
    }
}
