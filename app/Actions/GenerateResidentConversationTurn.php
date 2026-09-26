<?php

namespace App\Actions;

use App\Directors\PromptDirector;
use App\Enums\AssistantKind;
use App\Enums\ConversationStatus;
use App\Enums\Posture;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WorldSession;
use App\Services\AgentLoop\AgentLoopRunner;
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

    private const HISTORY_LIMIT = 30;

    public function __construct(
        private readonly AppendExpressionTags $appendExpressionTags,
        private readonly AppendWorldConversationContext $appendWorldConversationContext,
        private readonly BuildResidentWorldPrompt $buildResidentWorldPrompt,
        private readonly LlmResponseTagParser $tagParser,
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

        $world = $session->worldUser->world;
        $assistantUser = AssistantUser::where('user_id', $session->worldUser->user_id)->where('assistant_id', $speaker->id)->firstOrFail();

        $director = new PromptDirector($this->appendWorldConversationContext->handle($speaker, $world, $positions, $session));
        $director->append('talking with', $this->buildResidentWorldPrompt->conversationTurnInstruction($other->name));
        $excluded = ['opening_message', 'voice mode', 'image handling', 'OOC mode', 'conversations_with_others'];
        $this->appendExpressionTags->handle($director, $speaker, $excluded, $posturesByAssistantId[$speaker->id] ?? Posture::Standing);
        $director->except($excluded);
        $userChat = $assistantUser->conversations()->where('world_session_id', $session->id)->first();
        if ($userChat !== null) {
            $director->withLongTermMemory($userChat);
        }

        $history = $conversation->messages()->latest('id')->limit(self::HISTORY_LIMIT)->get()->reverse()
            ->map(fn (Message $message) => $message->speaker_id === $speaker->id
                ? ['role' => 'assistant', 'content' => (string) $message->content]
                : ['role' => 'user', 'content' => "{$other->name}: {$message->content}"])
            ->values()
            ->all();

        $llmManager = new LlmManager;
        $aiModel = $llmManager->resolveModelForAssistantUser($assistantUser);
        $llm = $aiModel ? $llmManager->fromModel($aiModel) : $llmManager->fromConfig();
        $stop = new StopConversationTool;
        $result = (new AgentLoopRunner($llm, $aiModel?->supports_tools ? [$stop] : []))->run(
            assistant: $speaker,
            messages: [['role' => 'system', 'content' => $director->build()], ...$history],
            conversation: $conversation,
        );

        $parsed = $this->tagParser->parse($result->content, $speaker);
        $line = $this->tagParser->stripStrayTags($parsed['content']);
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $line,
            'speaker_type' => $speaker->getMorphClass(),
            'speaker_id' => $speaker->id,
            'expression' => Message::expressionFrom($parsed, $stop->stopped ? ['verb' => 'stop_conversation'] : null, $this->tagParser->strayTags($parsed['content'])),
        ]);

        if ($stop->stopped) {
            $conversation->update(['status' => ConversationStatus::Paused]);

            return ['status' => 'paused', 'message' => $message, 'pose' => $parsed['pose']];
        }

        $conversation->touch();

        return ['status' => 'spoke', 'message' => $message, 'pose' => $parsed['pose']];
    }
}
