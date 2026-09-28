<?php

namespace App\Actions;

use App\Enums\InventoryHolder;
use App\Models\Inventory;
use App\Models\Region;
use App\Models\StartingInventory;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Support\Facades\DB;

class ResolveInventory
{
    public function forPlayer(WorldSession $session): Inventory
    {
        return $this->resolve($session, ['holder' => InventoryHolder::Player, 'world_resident_id' => null, 'region_id' => null, 'object_id' => null]);
    }

    public function forResident(WorldSession $session, WorldResident $resident): Inventory
    {
        return $this->resolve($session, ['holder' => InventoryHolder::Resident, 'world_resident_id' => $resident->id, 'region_id' => null, 'object_id' => null]);
    }

    public function forObject(WorldSession $session, Region $region, string $objectId): Inventory
    {
        return $this->resolve($session, ['holder' => InventoryHolder::Object, 'world_resident_id' => null, 'region_id' => $region->id, 'object_id' => $objectId]);
    }

    /**
     * Copies a starting inventory, with its items, into the session.
     */
    public function copy(StartingInventory $starting, WorldSession $session): Inventory
    {
        $inventory = $session->inventories()->create([
            'holder' => $starting->holder,
            'world_resident_id' => $starting->world_resident_id,
            'region_id' => $starting->region_id,
            'object_id' => $starting->object_id,
            'credits' => $starting->credits,
        ]);

        foreach ($starting->items as $item) {
            $inventory->items()->create([
                'item_id' => $item->item_id,
                'quantity' => $item->quantity,
                'for_sale' => $item->for_sale,
                'takeable' => $item->takeable,
            ]);
        }

        return $inventory;
    }

    /**
     * The holder's inventory in the session. A session started before the holder
     * had one (an older session, or a resident added later) gets it copied from the
     * holder's starting inventory the first time it is needed.
     *
     * @param  array{holder: InventoryHolder, world_resident_id: ?int, region_id: ?int, object_id: ?string}  $key
     */
    private function resolve(WorldSession $session, array $key): Inventory
    {
        $existing = $session->inventories()->where($key)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($session, $key): Inventory {
            WorldSession::whereKey($session->id)->lockForUpdate()->first();

            $existing = $session->inventories()->where($key)->first();
            if ($existing !== null) {
                return $existing;
            }

            $starting = StartingInventory::with('items')
                ->where('world_id', $session->worldUser->world_id)
                ->where($key)
                ->first();

            return $starting !== null
                ? $this->copy($starting, $session)
                : $session->inventories()->create([...$key, 'credits' => 0]);
        });
    }
}
