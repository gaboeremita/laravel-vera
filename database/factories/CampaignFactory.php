<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\World;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_id' => World::factory(),
            'key' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'definition' => [
                'description' => 'A longer story.',
                'rubric' => [
                    'guidance' => 'Judge the whole story.',
                    'dimensions' => [['name' => 'resolve', 'description' => 'How the player saw it through.']],
                    'tiers' => [],
                ],
            ],
        ];
    }
}
