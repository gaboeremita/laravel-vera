<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\DescribeHandover;
use App\Actions\ResolveInventory;
use App\Contracts\AgentTool;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\WorldResident;
use App\Models\WorldSession;
use RuntimeException;

/**
 * Creator mode's control over what anyone holds: grant creates credits and
 * items from nothing, remove destroys them.
 */
abstract class CreatorInventoryTool implements AgentTool
{
    public const USER = 'the user';

    public function __construct(protected readonly WorldSession $session) {}

    public function parameters(): array
    {
        $worldId = $this->session->worldUser->world_id;

        return [
            'type' => 'object',
            'properties' => [
                'holder' => ['type' => 'string', 'enum' => [self::USER, ...WorldResident::with('assistant')->where('world_id', $worldId)->get()->map(fn (WorldResident $resident) => $resident->assistant->name)->all()]],
                'credits' => ['type' => 'integer', 'minimum' => 0],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'item' => ['type' => 'string', 'enum' => Item::where('world_id', $worldId)->orderBy('name')->pluck('name')->all()],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                        'required' => ['item', 'quantity'],
                    ],
                ],
            ],
            'required' => ['holder'],
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

    protected function holder(string $name): Inventory
    {
        $resolveInventory = app(ResolveInventory::class);
        if (mb_strtolower(trim($name)) === self::USER) {
            return $resolveInventory->forPlayer($this->session);
        }

        $resident = WorldResident::where('world_id', $this->session->worldUser->world_id)
            ->whereHas('assistant', fn ($query) => $query->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))]))
            ->first()
            ?? throw new RuntimeException(sprintf('There is no one called "%s" in this world.', $name));

        return $resolveInventory->forResident($this->session, $resident);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{credits: int, items: array<int, int>}
     */
    protected function amounts(array $arguments): array
    {
        $worldId = $this->session->worldUser->world_id;
        $items = [];
        foreach ($arguments['items'] ?? [] as $entry) {
            $item = Item::where('world_id', $worldId)->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) ($entry['item'] ?? '')))])->first()
                ?? throw new RuntimeException(sprintf('There is no item called "%s" in this world.', $entry['item'] ?? ''));
            $items[$item->id] = ($items[$item->id] ?? 0) + max(1, (int) ($entry['quantity'] ?? 1));
        }
        $credits = max(0, (int) ($arguments['credits'] ?? 0));
        if ($credits === 0 && $items === []) {
            throw new RuntimeException('Name the credits or items.');
        }

        return ['credits' => $credits, 'items' => $items];
    }

    /**
     * @param  array{credits: int, items: array<int, int>}  $amounts
     */
    protected function described(array $amounts): string
    {
        return app(DescribeHandover::class)->handle($amounts['credits'], $amounts['items']);
    }
}
