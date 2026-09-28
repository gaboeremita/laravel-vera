<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_id' => Inventory::factory(),
            'item_id' => Item::factory(),
            'quantity' => 1,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn () => ['quantity' => null]);
    }

    public function forSale(): static
    {
        return $this->state(fn () => ['for_sale' => true]);
    }

    public function takeable(): static
    {
        return $this->state(fn () => ['takeable' => true]);
    }
}
