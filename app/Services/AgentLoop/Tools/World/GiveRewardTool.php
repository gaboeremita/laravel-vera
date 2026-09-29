<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Models\ResidentFeeling;

/**
 * What a quest's reward giver hands the user once the quest is complete, and
 * for a resident giver how their feelings about the user change. Its call is
 * read directly from the reply; items can only be ones the giver holds, never
 * more than they hold, credits never more than a counted balance, and each
 * feeling moves at most 3 points.
 */
class GiveRewardTool implements AgentTool
{
    /**
     * @param  array<string, array{id: int, quantity: ?int}>  $held  what the giver holds, by item name; a null quantity is plenty
     * @param  ?int  $credits  the giver's balance, or null when theirs isn't counted
     * @param  bool  $withFeelings  whether the giver is a resident, whose feelings can change
     */
    public function __construct(
        private readonly array $held,
        private readonly ?int $credits,
        private readonly bool $withFeelings = false,
    ) {}

    private const FEELING_STEP = 3;

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
                ...($this->withFeelings ? ['feelings' => [
                    'type' => 'object',
                    'description' => 'How your feelings about the user change after this quest, as the amount to add to each (negative to lower); only the ones that change.',
                    'properties' => collect(ResidentFeeling::FEELINGS)->mapWithKeys(fn (string $feeling) => [$feeling => ['type' => 'number', 'minimum' => -self::FEELING_STEP, 'maximum' => self::FEELING_STEP]])->all(),
                ]] : []),
            ],
            'required' => ['credits', 'items', 'line'],
        ];
    }

    /**
     * @return array{credits: int, items: array<int, int>, line: string, feelings: array<string, float>}
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
            'feelings' => $this->withFeelings
                ? collect(ResidentFeeling::FEELINGS)
                    ->filter(fn (string $feeling) => is_numeric($arguments['feelings'][$feeling] ?? null))
                    ->mapWithKeys(fn (string $feeling) => [$feeling => max(-self::FEELING_STEP, min(self::FEELING_STEP, (float) $arguments['feelings'][$feeling]))])
                    ->all()
                : [],
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
