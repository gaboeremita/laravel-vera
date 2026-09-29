<?php

namespace App\Actions;

use App\Models\Item;

class DescribeHandover
{
    /**
     * "50 credits, 2 Bread and the Iron key", for lines a character reads.
     *
     * @param  array<int, int>  $items  quantity by item id
     */
    public function handle(int $credits, array $items): string
    {
        $names = Item::whereKey(array_keys($items))->pluck('name', 'id');
        $parts = collect($items)
            ->filter(fn (int $quantity) => $quantity > 0)
            ->map(fn (int $quantity, int $itemId) => $quantity === 1 ? "the {$names[$itemId]}" : "{$quantity} {$names[$itemId]}")
            ->values();
        if ($credits > 0) {
            $parts->prepend($credits === 1 ? '1 credit' : "{$credits} credits");
        }

        return $parts->count() > 1 ? $parts->slice(0, -1)->implode(', ').' and '.$parts->last() : (string) $parts->first();
    }
}
