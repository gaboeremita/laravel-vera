<?php

namespace App\Actions;

use App\Exceptions\InsufficientInventory;
use App\Models\CreditTransaction;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * The only way items and credits move. A null side is outside every inventory:
 * a null giver creates what it gives (an item releasing its contents), a null
 * receiver destroys it (an item used up).
 */
class TransferInventory
{
    /**
     * @param  array<int, int>  $items  quantity by item id
     * @param  bool  $byCreator  whether creator mode made the change
     *
     * @throws InsufficientInventory
     */
    public function handle(?Inventory $from, ?Inventory $to, int $credits, array $items, string $reason, bool $byCreator = false): void
    {
        $items = array_filter($items, fn (int $quantity) => $quantity > 0);
        if ($credits <= 0 && $items === []) {
            return;
        }

        DB::transaction(function () use ($from, $to, $credits, $items, $reason, $byCreator): void {
            $locked = Inventory::whereKey(array_filter([$from?->id, $to?->id]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $giver = $from !== null ? $locked[$from->id] : null;
            $receiver = $to !== null ? $locked[$to->id] : null;

            if ($giver !== null) {
                $this->ensureEnough($giver, $credits, $items);
            }

            if ($credits > 0) {
                if ($giver !== null && $giver->credits !== null) {
                    $giver->decrement('credits', $credits);
                }
                if ($receiver !== null && $receiver->credits !== null) {
                    $receiver->increment('credits', $credits);
                }
                $this->recordCredits($from, $to, $credits, $reason, $byCreator);
            }

            foreach ($items as $itemId => $quantity) {
                if ($giver !== null) {
                    $this->take($giver, $itemId, $quantity);
                }
                if ($receiver !== null) {
                    $this->add($receiver, $itemId, $quantity);
                }
            }
        });

        $from?->refresh();
        $to?->refresh();
    }

    /**
     * @param  array<int, int>  $items
     *
     * @throws InsufficientInventory
     */
    private function ensureEnough(Inventory $giver, int $credits, array $items): void
    {
        if ($credits > 0 && $giver->credits !== null && $giver->credits < $credits) {
            throw new InsufficientInventory(sprintf('%s has only %d credits.', $giver->displayName(), $giver->credits));
        }

        foreach ($items as $itemId => $quantity) {
            $held = $giver->items()->where('item_id', $itemId)->lockForUpdate()->first();
            if ($held === null || ($held->quantity !== null && $held->quantity < $quantity)) {
                throw new InsufficientInventory(sprintf('%s has only %d %s.', $giver->displayName(), $held?->quantity ?? 0, Item::find($itemId)?->name ?? 'of that item'));
            }
        }
    }

    private function take(Inventory $giver, int $itemId, int $quantity): void
    {
        $held = $giver->items()->where('item_id', $itemId)->first();
        if ($held->quantity === null) {
            return;
        }

        $held->quantity === $quantity ? $held->delete() : $held->decrement('quantity', $quantity);
    }

    private function add(Inventory $receiver, int $itemId, int $quantity): void
    {
        /** @var ?InventoryItem $held */
        $held = $receiver->items()->where('item_id', $itemId)->first();
        if ($held === null) {
            $receiver->items()->create(['item_id' => $itemId, 'quantity' => $quantity]);

            return;
        }

        if ($held->quantity !== null) {
            $held->increment('quantity', $quantity);
        }
    }

    private function recordCredits(?Inventory $from, ?Inventory $to, int $credits, string $reason, bool $byCreator): void
    {
        CreditTransaction::create([
            'world_session_id' => ($from ?? $to)->world_session_id,
            'from_inventory_id' => $from?->id,
            'to_inventory_id' => $to?->id,
            'from_name' => $from?->displayName() ?? $reason,
            'to_name' => $to?->displayName() ?? $reason,
            'amount' => $credits,
            'reason' => $reason,
            'by_creator' => $byCreator,
        ]);
    }
}
