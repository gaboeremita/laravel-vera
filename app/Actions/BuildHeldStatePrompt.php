<?php

namespace App\Actions;

use App\Models\Inventory;
use App\Models\InventoryItem;

class BuildHeldStatePrompt
{
    /**
     * The state entries of the items the resident holds, for their own prompt.
     * Each one takes precedence over the rest of their character while they hold the item.
     */
    public function handle(Inventory $inventory): ?string
    {
        $states = $inventory->items()
            ->whereHas('item', fn ($query) => $query->whereNotNull('state_entry_id'))
            ->with('item.stateEntry')
            ->get()
            ->map(fn (InventoryItem $held): string => "You hold {$held->item->name}. While you hold it, this takes precedence over everything else about your character:\n{$held->item->stateEntry->content}");

        return $states->isEmpty() ? null : $states->implode("\n\n");
    }
}
