<?php

namespace App\Actions\Activities\Effects;

use App\Models\Inventory;
use App\Models\Item;
use App\Models\World;

/**
 * An effect that moves a list of items, each with a quantity.
 */
abstract class ItemsEffect extends Effect
{
    public function validate(array $config, World $world): array
    {
        $entries = $config['items'] ?? null;
        if (! is_array($entries) || $entries === []) {
            return ['Choose at least one item.'];
        }

        $worldItemIds = $world->items()->pluck('id')->all();
        $problems = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! in_array((int) ($entry['itemId'] ?? 0), $worldItemIds, true)) {
                $problems[] = 'Every item must be one of the world\'s items.';
            } elseif (! is_int($entry['quantity'] ?? null) || $entry['quantity'] < 1) {
                $problems[] = 'Every quantity must be a whole number of at least 1.';
            }
        }
        if (count(array_unique(array_map(fn ($entry) => (int) ($entry['itemId'] ?? 0), $entries))) !== count($entries)) {
            $problems[] = 'Each item can be listed once.';
        }

        return array_values(array_unique($problems));
    }

    public function normalize(array $config): array
    {
        return ['items' => collect($config['items'] ?? [])->map(fn (array $entry) => ['itemId' => (int) $entry['itemId'], 'quantity' => (int) $entry['quantity']])->values()->all()];
    }

    public function itemIds(array $config): array
    {
        return array_keys($this->quantities($config));
    }

    public function withoutItem(array $config, int $itemId): ?array
    {
        $kept = collect($config['items'] ?? [])->reject(fn (array $entry) => (int) $entry['itemId'] === $itemId)->values()->all();

        return $kept === [] ? null : [...$config, 'items' => $kept];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, int> quantity by item id
     */
    protected function quantities(array $config): array
    {
        return collect($config['items'] ?? [])->mapWithKeys(fn (array $entry) => [(int) $entry['itemId'] => (int) $entry['quantity']])->all();
    }

    /**
     * The items and quantities, as a phrase for the narrator.
     *
     * @param  array<string, mixed>  $config
     */
    protected function listed(array $config): string
    {
        $names = Item::whereKey(array_keys($this->quantities($config)))->pluck('name', 'id');

        return collect($this->quantities($config))
            ->map(fn (int $quantity, int $itemId) => ($quantity > 1 ? "{$quantity} " : '').($names[$itemId] ?? 'an item'))
            ->implode(', ');
    }

    /**
     * The first item the inventory holds too few of, with how many are needed.
     *
     * @param  array<string, mixed>  $config
     * @return ?array{item: ?Item, quantity: int}
     */
    protected function shortOf(array $config, Inventory $inventory): ?array
    {
        foreach ($this->quantities($config) as $itemId => $quantity) {
            $held = $inventory->items()->where('item_id', $itemId)->first();
            if ($held === null || ($held->quantity !== null && $held->quantity < $quantity)) {
                return ['item' => Item::find($itemId), 'quantity' => $quantity];
            }
        }

        return null;
    }
}
