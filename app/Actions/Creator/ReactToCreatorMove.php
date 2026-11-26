<?php

namespace App\Actions\Creator;

use App\Actions\AppendExpressionTags;
use App\Actions\AppendWorldConversationContext;
use App\Actions\BuildConversationHistory;
use App\Actions\BuildSentimentsPrompt;
use App\Actions\ComposeChatRequest;
use App\Actions\ResolveResidentRegion;
use App\Actions\ResolveWorldState;
use App\Directors\PromptDirector;
use App\Enums\TurnMode;
use App\Enums\TurnSection;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Region;
use App\Models\Sentiment;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\AgentLoop\Tools\AdjustSentimentsTool;
use App\Services\LlmProviders\LlmManager;
use App\Services\LlmResponseTagParser;

/**
 * A resident's own reaction, in character, to what the creator made happen to
 * them: being put somewhere else, set walking to a zone, struck into a pose,
 * held still or let go.
 * The model gets their prompt and world context with what happened to them,
 * told as something that came from outside them; it says how they take it,
 * and may change how they feel about the user.
 */
class ReactToCreatorMove
{
    public const MOVES = ['summoned', 'followed', 'sent', 'sent_home', 'moved', 'walked'];

    public const KINDS = [...self::MOVES, 'posed', 'frozen', 'unfrozen'];

    public function __construct(
        private readonly AppendExpressionTags $appendExpressionTags,
        private readonly AppendWorldConversationContext $appendWorldConversationContext,
        private readonly BuildConversationHistory $buildConversationHistory,
        private readonly ComposeChatRequest $composeChatRequest,
        private readonly ResolveResidentRegion $resolveResidentRegion,
        private readonly ResolveWorldState $resolveWorldState,
        private readonly LlmResponseTagParser $tagParser,
    ) {}

    public function handle(WorldSession $session, WorldResident $resident, string $kind, ?Region $fromRegion = null, ?string $fromZoneName = null, ?string $poseName = null, ?string $posture = null, ?string $toZoneName = null): Message
    {
        $assistant = $resident->assistant;
        $assistantUser = AssistantUser::where('user_id', $session->worldUser->user_id)->where('assistant_id', $assistant->id)->firstOrFail();
        $conversation = Conversation::withResident($session, $resident);
        $region = $this->resolveResidentRegion->handle($session, $resident);
        $position = $session->residentStates()->where('world_resident_id', $resident->id)->value('position') ?? $resident->position;
        $zone = $position !== null ? $this->resolveWorldState->locate($region->layout ?? [], $position)['zone'] : null;

        $director = new PromptDirector($assistant->prompt);
        $this->appendWorldConversationContext->handle($director, $assistant, $region, $position !== null ? ['residents' => [$resident->id => $position]] : null, $session, current: $conversation);
        $excluded = ['opening_message', 'voice mode', 'image handling', 'OOC mode', 'secret trigger', 'creator mode'];
        $this->appendExpressionTags->handle($director, $assistant, $excluded);
        $director->except($excluded);
        $director->withLongTermMemory($conversation);

        $sentiment = Sentiment::of($conversation);
        $llmManager = new LlmManager;
        $aiModel = $llmManager->resolveModelForAssistantUser($assistantUser);
        $canUseTools = (bool) $aiModel?->supports_tools;
        $sentimentsPrompt = app(BuildSentimentsPrompt::class)->handle($sentiment, TurnMode::InCharacter, $canUseTools);
        if ($sentimentsPrompt !== null) {
            $director->addToTurn(TurnSection::CurrentState, 'sentiments', $sentimentsPrompt);
        }
        $director->addToTurn(TurnSection::CurrentState, 'what just happened', $this->whatHappened($kind, $fromRegion !== null ? $this->place($fromRegion, $fromZoneName) : null, $this->place($region, $toZoneName ?? $zone['name'] ?? null), $poseName, $posture));

        $llm = $aiModel ? $llmManager->fromModel($aiModel) : $llmManager->fromConfig();
        $tools = $canUseTools && $sentiment->names() !== [] ? [new AdjustSentimentsTool($sentiment, TurnMode::InCharacter)] : [];
        $result = (new AgentLoopRunner($llm, $tools))->run(
            assistant: $assistant,
            messages: $this->composeChatRequest->handle($director->build(), $this->buildConversationHistory->handle($conversation, $assistant), null),
            conversation: $conversation,
            conversationKey: $conversation->providerSessionKey(),
        );

        $parsed = $this->tagParser->parse($result->content, $assistant);
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $this->tagParser->stripStrayTags($parsed['content']),
            'expression' => Message::expressionFrom($parsed, null, $this->tagParser->strayTags($parsed['content'])),
        ]);
        $conversation->touch();

        return $message;
    }

    private function place(Region $region, ?string $zoneName): string
    {
        return $zoneName !== null ? "{$zoneName}, in {$region->name}" : $region->name;
    }

    private function whatHappened(string $kind, ?string $from, string $to, ?string $poseName, ?string $posture): string
    {
        $event = match ($kind) {
            'moved' => $from === $to
                ? "A moment ago you were standing elsewhere in {$to}. Something lifted you and set you down a few steps away, as if a hand you could not see had carried you."
                : "A moment ago you were in {$from}. Something lifted you and set you down in {$to}, as if a hand you could not see had carried you.",
            'walked' => $from === $to
                ? "Your legs have just started carrying you across {$to}. The urge to go arrived on its own, as if someone had placed it in you."
                : "Your legs have just started carrying you from {$from} toward {$to}. The urge to go arrived on its own, as if someone had placed it in you.",
            'posed' => "Your body just moved by itself into \"{$poseName}\" while {$posture}. The impulse came from outside you, as if someone else had willed it.",
            'frozen' => 'Your body has just stopped where it stands and holds you there, while your voice stays your own. The stillness came from outside you, as if someone else had willed it.',
            'unfrozen' => 'Your body is yours to move again. Whatever held you still has let go, as suddenly as it came.',
            'summoned' => "A moment ago you were in {$from}. Something happened, and now you are in {$to}, right beside the user.",
            'followed' => "A moment ago you were in {$from}, following the user. Something happened, and now you are both in {$to}.",
            'sent_home' => "A moment ago you were in {$from}. Something happened, and now you are back home in {$to}.",
            default => "A moment ago you were in {$from}. Something happened, and now you are in {$to}.",
        };

        $moment = in_array($kind, ['moved', 'walked', 'posed', 'frozen', 'unfrozen'], true)
            ? 'It happened in an instant, and you lived through it. Imagine what that moment was like for you, in a way that fits who you are and this world: what you saw, heard or felt as it took hold of you. React in character, in two to four sentences, with any action in asterisks: let what it was like come through in what you say or do, and how it leaves you feeling.'
            : 'The change took you in an instant, and you lived through it. Imagine what that moment was like for you, in a way that fits who you are and this world: what you saw, heard or felt as one place became the other. React in character, in two to four sentences, with any action in asterisks: let what the jump was like come through in what you say or do, and how it leaves you feeling.';

        return "{$event}\n{$moment}";
    }
}
