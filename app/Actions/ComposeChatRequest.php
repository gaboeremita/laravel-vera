<?php

namespace App\Actions;

use App\DTOs\PromptLayout;

/**
 * Assembles the messages of a chat request from least to most likely to
 * change, with a cache point after the unchanging sections, after the
 * occasional sections and after the previous messages.
 */
class ComposeChatRequest
{
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
            ['type' => 'text', 'text' => $layout->unchanging(), 'cachePoint' => true],
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
        if ($currentMessage === null) {
            return $turn !== '' ? ['role' => 'user', 'content' => [['type' => 'text', 'text' => $turn]]] : null;
        }

        $texts = array_filter([$turn, (string) ($currentMessage['content'] ?? '')], fn (string $text) => $text !== '');
        $currentMessage['content'] = array_map(fn (string $text) => ['type' => 'text', 'text' => $text], array_values($texts));

        return $currentMessage;
    }
}
