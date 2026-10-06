<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;

/**
 * What a quest's reward giver hands the user once the quest is complete, and
 * for a resident giver how their sentiments about the user change. Its call
 * is read directly from the reply; items can only be ones the giver holds,
 * never more than they hold, credits never more than a counted balance, and
 * each sentiment moves at most 3 points.
 */
class GiveRewardTool implements AgentTool
{
    /**
     * @param  array<string, array{id: int, quantity: ?int}>  $held  what the giver holds, by item name; a null quantity is plenty
     * @param  ?int  $credits  the giver's balance, or null when theirs isn't counted
     * @param  array<int, string>  $sentiments  the names of the giver's sentiments that can change; empty for an object
     */
    public function __construct(
        private readonly array $held,
        private readonly ?int $credits,
        private readonly array $sentiments = [],
    ) {}

    private const SENTIMENT_STEP = 3;

    public function name(): string
    {
        return 'give_reward';
    }

    public function description(): string
    {
        return 'Hands the user their reward for the quest: the credits and items you decide on, from what you hold, with what you say or what happens as you do.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'credits' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Credits to give; 0 for none.'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'item' => ['type' => 'string', ...($this->held !== [] ? ['enum' => array_keys($this->held)] : [])],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                        'required' => ['item', 'quantity'],
                    ],
                ],
                'line' => ['type' => 'string', 'description' => 'One to three sentences said or shown to the user as the reward is handed over.'],
                ...($this->sentiments !== [] ? ['sentiments' => [
                    'type' => 'object',
                    'description' => 'How your feelings about the user change after this quest, as the amount to add to each (negative to lower); only the ones that change.',
                    'properties' => collect($this->sentiments)->mapWithKeys(fn (string $name) => [$name => ['type' => 'number', 'minimum' => -self::SENTIMENT_STEP, 'maximum' => self::SENTIMENT_STEP]])->all(),
                ]] : []),
            ],
            'required' => ['credits', 'items', 'line'],
        ];
    }

    /**
     * @return array{credits: int, items: array<int, int>, line: string, sentiments: array<string, float>}
     */
    public function handle(array $arguments): array
    {
        $credits = max(0, (int) ($arguments['credits'] ?? 0));
        if ($this->credits !== null) {
            $credits = min($credits, $this->credits);
        }

        $items = [];
        foreach ($arguments['items'] ?? [] as $entry) {
            $held = is_array($entry) && is_string($entry['item'] ?? null) ? ($this->held[$entry['item']] ?? null) : null;
            if ($held === null) {
                continue;
            }
            $wanted = ($items[$held['id']] ?? 0) + max(0, (int) ($entry['quantity'] ?? 0));
            $items[$held['id']] = $held['quantity'] === null ? $wanted : min($wanted, $held['quantity']);
        }

        return [
            'credits' => $credits,
            'items' => array_filter($items),
            'line' => trim((string) ($arguments['line'] ?? '')),
            'sentiments' => collect($this->sentiments)
                ->filter(fn (string $name) => is_numeric($arguments['sentiments'][$name] ?? null))
                ->mapWithKeys(fn (string $name) => [$name => max(-self::SENTIMENT_STEP, min(self::SENTIMENT_STEP, (float) $arguments['sentiments'][$name]))])
                ->all(),
        ];
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
