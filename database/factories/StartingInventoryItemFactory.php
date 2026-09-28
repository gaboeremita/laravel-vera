<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StartingInventory;
use App\Models\StartingInventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StartingInventoryItem>
 */
class StartingInventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'starting_inventory_id' => StartingInventory::factory(),
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
