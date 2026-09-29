<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemTransfer;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemTransfer>
 */
class ItemTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'item_id' => Item::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'reason' => 'gift',
            'by_creator' => false,
        ];
    }
}
