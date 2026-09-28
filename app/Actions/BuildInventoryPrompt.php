<?php

namespace App\Actions;

use App\Models\Inventory;
use App\Models\InventoryItem;

class BuildInventoryPrompt
{
    /**
     * What the character carries, for their own prompt.
     */
    public function handle(Inventory $inventory, bool $canAskUser = true): string
    {
        $credits = $inventory->credits === null ? 'as many credits as you need' : "{$inventory->credits} credits";
        $items = $inventory->items()->with('item')->get()->map(function (InventoryItem $held): string {
            $amount = $held->quantity === null ? 'plenty of' : ($held->quantity === 1 ? 'one' : (string) $held->quantity);
            $sale = $held->for_sale ? ' (for sale'.($held->item->base_price !== null ? ", usually {$held->item->base_price} credits each" : '').')' : '';

            return "{$amount} {$held->item->name}{$sale}: {$held->item->description}";
        });

        $carrying = $items->isEmpty() ? '' : " Your items:\n- ".$items->implode("\n- ");

        return "You carry {$credits}.{$carrying}\nWhat you carry stays private until you mention or show it. You decide in character what to give, sell, trade or keep, and at what price; hand things over with the give tool".($canAskUser ? ' and ask the user for credits or items with the ask_for tool' : '').'.';
    }
}
