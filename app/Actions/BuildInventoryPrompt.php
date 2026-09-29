<?php

namespace App\Actions;

use App\Models\Inventory;
use App\Models\InventoryItem;

class BuildInventoryPrompt
{
    /**
     * What the character carries, for their own prompt. Between residents
     * credits are only played at, so they are left out.
     */
    public function handle(Inventory $inventory, bool $talkingWithUser = true): string
    {
        $items = $inventory->items()->with('item')->get()->map(function (InventoryItem $held): string {
            $amount = $held->quantity === null ? 'plenty of' : ($held->quantity === 1 ? 'one' : (string) $held->quantity);
            $sale = $held->for_sale ? ' (for sale'.($held->item->base_price !== null ? ", usually {$held->item->base_price} credits for one {$held->item->name}" : '').')' : '';

            return "{$amount} {$held->item->name}{$sale}: {$held->item->description}";
        });

        $carrying = $items->isEmpty() ? '' : " Your items:\n- ".$items->implode("\n- ");

        if (! $talkingWithUser) {
            $carrying = $items->isEmpty() ? 'You carry no items.' : ltrim($carrying);

            return "{$carrying}\nWhat you carry stays private until you mention or show it. You decide in character what to give, sell, trade or keep. Between you and other residents nothing really costs credits: name a price if it fits the moment and play along with paying, but hand over what you agree on with the give tool for free.";
        }

        $credits = $inventory->credits === null ? 'as many credits as you need' : "{$inventory->credits} credits";

        return "You carry {$credits}.{$carrying}\nWhat you carry stays private until you mention or show it. You decide in character what to give, sell, trade or keep, and at what price; hand things over with the give tool and ask the user for credits or items with the ask_for tool.";
    }
}
