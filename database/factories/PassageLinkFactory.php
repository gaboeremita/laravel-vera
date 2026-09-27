<?php

namespace Database\Factories;

use App\Models\PassageLink;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PassageLink>
 */
class PassageLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory()->withLayout(),
            'passage_id' => 'studio-door',
            'target_region_id' => Region::factory()->withLayout(),
            'target_passage_id' => 'studio-door',
        ];
    }
}
