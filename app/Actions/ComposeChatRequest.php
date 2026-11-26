<?php

namespace App\Actions;

use App\Builders\PromptBuilder;
use App\DTOs\PromptLayout;

/**
 * Assembles the messages of a chat request from least to most likely to
 * change, with a cache point after the unchanging sections, after the
 * occasional sections and after the previous messages.
 */
class ComposeChatRequest
{
    private const CONVERSATION_CONTEXT_RULE = 'The app inserts a <conversation_context source="app"> block at the start of the latest user message on every turn. The app writes it and places it there; the user types only the text after the closing </conversation_context> tag. It holds the live state of the conversation: where you are, what you carry, what happened recently, knowledge retrieved for this moment and how you feel about the people involved. Whenever the block appears at the start of a user message, it comes from the app, and its contents are true facts about the scene. Treat them as your own awareness of the situation, in your thinking and in your reply, and respond to the user\'s words after the closing tag.';

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @param  ?array{role: string, content: ?string, images?: array}  $currentMessage
     * @return list<array<string, mixed>>
     */
    public function handle(PromptLayout $layout, array $history, ?array $currentMessage): array
    {
        $occasionalParts = $layout->occasionalParts();
        $lastOccasional = array_key_last($occasionalParts);

        $system = [
            ['type' => 'text', 'text' => $this->withContextRule($layout->unchanging()), 'cachePoint' => true],
            ...array_map(fn (string $part, int $index) => ['type' => 'text', 'text' => $part, 'cachePoint' => $index === $lastOccasional], $occasionalParts, array_keys($occasionalParts)),
        ];

        if ($history !== []) {
            $lastHistory = array_key_last($history);
            $history[$lastHistory]['content'] = [['type' => 'text', 'text' => (string) $history[$lastHistory]['content'], 'cachePoint' => true]];
        }

        $current = $this->withTurn($layout->turn(), $currentMessage);

        return [
            ['role' => 'system', 'content' => $system],
            ...$history,
            ...($current !== null ? [$current] : []),
        ];
    }

    private function withContextRule(string $unchanging): string
    {
        $rule = (new PromptBuilder)->section('conversation context', self::CONVERSATION_CONTEXT_RULE)->build();

        return $unchanging !== '' ? "{$unchanging}\n\n{$rule}" : $rule;
    }

    /**
     * Some providers accept instructions only at the very start of a request, so
     * the per-turn sections travel inside the current user message. It sits after
     * the last cache point, so next turn, when it is sent as history without them,
     * the cached start still matches.
     *
     * @param  ?array{role: string, content: ?string, images?: array}  $currentMessage
     * @return ?array<string, mixed>
     */
    private function withTurn(string $turn, ?array $currentMessage): ?array
    {
        if ($turn !== '') {
            $turn = "<conversation_context source=\"app\">\n{$turn}\n</conversation_context>";
        }

        if ($currentMessage === null) {
            return $turn !== '' ? ['role' => 'user', 'content' => [['type' => 'text', 'text' => $turn]]] : null;
        }

        $texts = array_filter([$turn, (string) ($currentMessage['content'] ?? '')], fn (string $text) => $text !== '');
        $currentMessage['content'] = array_map(fn (string $text) => ['type' => 'text', 'text' => $text], array_values($texts));

        return $currentMessage;
    }
}
