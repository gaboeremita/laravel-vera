<?php

namespace App\Actions;

use App\Enums\InventoryHolder;
use App\Models\StartingInventory;
use App\Models\World;
use Illuminate\Support\Facades\DB;

class SaveStartingInventory
{
    /**
     * Replaces a holder's starting inventory in the world.
     *
     * @param  array{holder: InventoryHolder, world_resident_id: ?int, region_id: ?int, object_id: ?string}  $key
     * @param  array<int, array{itemId: int, quantity: ?int, forSale?: bool, takeable?: bool}>  $items
     */
    public function handle(World $world, array $key, ?int $credits, array $items): StartingInventory
    {
        return DB::transaction(function () use ($world, $key, $credits, $items): StartingInventory {
            $starting = $world->startingInventories()->where($key)->first()
                ?? $world->startingInventories()->make($key);
            $starting->credits = $credits;
            $starting->save();

            $starting->items()->delete();
            foreach ($items as $entry) {
                $starting->items()->create([
                    'item_id' => $entry['itemId'],
                    'quantity' => $entry['quantity'],
                    'for_sale' => $entry['forSale'] ?? false,
                    'takeable' => $entry['takeable'] ?? false,
                ]);
            }

            return $starting->load('items');
        });
    }

    /**
     * @return array{credits: ?int, items: array<int, array{itemId: int, quantity: ?int, forSale: bool, takeable: bool}>}
     */
    public static function present(?StartingInventory $starting): array
    {
        return [
            'credits' => $starting === null ? 0 : $starting->credits,
            'items' => $starting === null ? [] : $starting->items->map(fn ($entry) => [
                'itemId' => $entry->item_id,
                'quantity' => $entry->quantity,
                'forSale' => $entry->for_sale,
                'takeable' => $entry->takeable,
            ])->values()->all(),
        ];
    }
}
