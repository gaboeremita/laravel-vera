<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\World;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_id' => World::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'base_price' => fake()->numberBetween(1, 50),
            'releases_credits' => 0,
            'releases_items' => [],
        ];
    }
}
