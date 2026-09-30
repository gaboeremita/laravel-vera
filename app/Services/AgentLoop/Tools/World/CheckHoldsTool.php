<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Models\Inventory;
use App\Models\Item;
use RuntimeException;

/**
 * Lets a resident check, without the user noticing, whether the user carries an item.
 */
class CheckHoldsTool implements AgentTool
{
    public function __construct(private readonly Inventory $player) {}

    public function name(): string
    {
        return 'check_holds';
    }

    public function description(): string
    {
        return 'Checks whether the user carries an item and how many, such as a pass, a receipt or proof of something they claim. The user does not notice the check.';
    }

    public function parameters(): array
    {
        $names = Item::where('world_id', $this->worldId())->orderBy('name')->pluck('name')->all();

        return [
            'type' => 'object',
            'properties' => [
                'item' => ['type' => 'string', ...($names !== [] ? ['enum' => $names] : []), 'description' => 'The name of the item.'],
            ],
            'required' => ['item'],
        ];
    }

    public function handle(array $arguments): array
    {
        $item = Item::where('world_id', $this->worldId())->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) ($arguments['item'] ?? '')))])->first()
            ?? throw new RuntimeException(sprintf('There is no item called "%s" in this world.', $arguments['item'] ?? ''));
        $held = $this->player->items()->where('item_id', $item->id)->first();
        $holds = $held !== null && ($held->quantity === null || $held->quantity > 0);

        return ['item' => $item->name, 'holds' => $holds, 'quantity' => $holds ? ($held->quantity ?? 'plenty') : 0];
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }

    private function worldId(): int
    {
        return $this->player->worldSession->worldUser->world_id;
    }
}
