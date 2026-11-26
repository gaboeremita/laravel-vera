<?php

namespace App\Actions;

use App\Models\Inventory;
use App\Models\InventoryItem;

class BuildInventoryPrompt
{
    /**
     * What the resident carries, for their own prompt. A resident has no
     * credit balance: with the user they pay any amount, and between
     * residents credits are only played at.
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

            return "{$carrying}\nWhat you carry stays private until you mention or show it. You decide in character what to give, sell, trade or keep. Between you and other residents nothing really costs credits: name a price if it fits the moment and play along with paying, but hand over what you agree on with the give tool for free, in the same reply where you say you hand it over.";
        }

        return "You can pay or give the user any amount of credits the moment calls for.{$carrying}\nWhat you carry stays private until you mention or show it. You decide in character what to give, sell, trade or keep, and at what price. Credits and items change hands only through your tools: ask the user for credits or items with the ask_for tool, and hand over credits or items with the give tool in the same reply where you say you hand them over. Once the user has paid what you asked, hand over what they paid for with the give tool in that reply.";
    }
}
