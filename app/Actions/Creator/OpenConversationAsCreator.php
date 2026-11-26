<?php

namespace App\Actions\Creator;

use App\Actions\AppendExpressionTags;
use App\Actions\AppendWorldConversationContext;
use App\Actions\BuildConversationHistory;
use App\Actions\ComposeChatRequest;
use App\Actions\ResolveResidentRegion;
use App\Directors\PromptDirector;
use App\Enums\TurnSection;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\LlmProviders\LlmManager;
use App\Services\LlmResponseTagParser;

/**
 * The line a resident opens with when the creator sends them to talk to
 * another resident: written in character from their prompt, world context
 * and what the two of them said before, with the urge to go over told as
 * something that came from outside them.
 */
class OpenConversationAsCreator
{
    public function __construct(
        private readonly AppendExpressionTags $appendExpressionTags,
        private readonly AppendWorldConversationContext $appendWorldConversationContext,
        private readonly BuildConversationHistory $buildConversationHistory,
        private readonly ComposeChatRequest $composeChatRequest,
        private readonly ResolveResidentRegion $resolveResidentRegion,
        private readonly LlmResponseTagParser $tagParser,
    ) {}

    /**
     * @return array{line: string, expression: ?array<string, mixed>}
     */
    public function handle(WorldSession $session, WorldResident $starter, WorldResident $other): array
    {
        $starter->loadMissing('assistant');
        $other->loadMissing('assistant');
        $assistant = $starter->assistant;
        $assistantUser = AssistantUser::where('user_id', $session->worldUser->user_id)->where('assistant_id', $assistant->id)->firstOrFail();
        $userChat = Conversation::withResident($session, $starter);
        $region = $this->resolveResidentRegion->handle($session, $starter);

        $earlier = Conversation::between($assistant, $other->assistant)->where('world_session_id', $session->id)->first();

        $director = new PromptDirector($assistant->prompt);
        $this->appendWorldConversationContext->handle($director, $assistant, $region, null, $session, current: $earlier);
        $excluded = ['opening_message', 'voice mode', 'image handling', 'OOC mode', 'secret trigger', 'creator mode'];
        $this->appendExpressionTags->handle($director, $assistant, $excluded);
        $director->except($excluded);
        $director->withLongTermMemory($userChat);
        $director->addToTurn(TurnSection::CurrentState, 'what moves you', $this->urge($other->assistant->name));

        $history = $earlier === null ? [] : array_map(fn (Message $message) => $message->speaker_id === $assistant->id
            ? ['role' => 'assistant', 'content' => (string) $message->content]
            : ['role' => 'user', 'content' => "{$other->assistant->name}: {$message->content}"], $this->buildConversationHistory->residentMessages($earlier));

        $llmManager = new LlmManager;
        $aiModel = $llmManager->resolveModelForAssistantUser($assistantUser);
        $llm = $aiModel ? $llmManager->fromModel($aiModel) : $llmManager->fromConfig();
        $result = (new AgentLoopRunner($llm, []))->run(
            assistant: $assistant,
            messages: $this->composeChatRequest->handle($director->build(), $history, null),
            conversation: $userChat,
            conversationKey: $userChat->providerSessionKey(),
        );

        $parsed = $this->tagParser->parse($result->content, $assistant);

        return [
            'line' => $this->tagParser->stripStrayTags($parsed['content']),
            'expression' => Message::expressionFrom($parsed, null, $this->tagParser->strayTags($parsed['content'])),
        ];
    }

    private function urge(string $otherName): string
    {
        return "Something outside you has just moved you to go over to {$otherName} and start talking with them; the urge arrived on its own, as if someone had placed it in you. Reply with the first thing you say to {$otherName} when you reach them, in your own voice: one to three sentences, with any action in asterisks. Let a trace of that outside pull show if it fits who you are. If you have talked before, pick up from where you left off.";
    }
}
