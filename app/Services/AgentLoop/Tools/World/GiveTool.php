<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\DescribeHandover;
use App\Actions\TransferInventory;
use App\Contracts\AgentTool;
use App\Models\Inventory;
use App\Models\InventoryItem;
use RuntimeException;

class GiveTool implements AgentTool
{
    /** @var array<int, array{credits: int, items: array<int, int>}> */
    public array $given = [];

    public function __construct(
        private readonly Inventory $giver,
        private readonly Inventory $receiver,
        private readonly string $receiverName,
    ) {}

    public function name(): string
    {
        return 'give';
    }

    public function description(): string
    {
        return "Hands {$this->receiverName} credits or items you carry, right now. Use it when you decide to give, pay, reward or trade; say what you hand over in your reply as well.";
    }

    public function parameters(): array
    {
        $names = $this->giver->items()->with('item')->get()->map(fn (InventoryItem $held) => $held->item->name)->values()->all();

        return [
            'type' => 'object',
            'properties' => [
                'credits' => ['type' => 'integer', 'minimum' => 0, 'description' => 'How many credits to hand over.'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'item' => ['type' => 'string', ...($names !== [] ? ['enum' => $names] : []), 'description' => 'The name of an item you carry.'],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                        'required' => ['item', 'quantity'],
                    ],
                ],
            ],
        ];
    }

    public function handle(array $arguments): array
    {
        $credits = max(0, (int) ($arguments['credits'] ?? 0));
        $items = [];
        foreach ($arguments['items'] ?? [] as $entry) {
            $held = $this->giver->items()->with('item')->get()->first(fn (InventoryItem $candidate) => mb_strtolower($candidate->item->name) === mb_strtolower(trim((string) ($entry['item'] ?? ''))));
            if ($held === null) {
                throw new RuntimeException(sprintf('You carry no "%s".', $entry['item'] ?? ''));
            }
            $items[$held->item_id] = ($items[$held->item_id] ?? 0) + max(1, (int) ($entry['quantity'] ?? 1));
        }
        if ($credits === 0 && $items === []) {
            throw new RuntimeException('Name the credits or items to hand over.');
        }

        app(TransferInventory::class)->handle($this->giver, $this->receiver, $credits, $items, 'gift');
        $this->given[] = ['credits' => $credits, 'items' => $items];

        return ['status' => 'given', 'note' => "You handed {$this->receiverName} ".app(DescribeHandover::class)->handle($credits, $items).'.'];
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
