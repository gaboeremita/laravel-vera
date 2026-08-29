<?php

namespace Database\Factories;

use App\Models\Assistant;
use App\Models\Pose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pose>
 */
class PoseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_id' => Assistant::factory(),
            'name' => $this->faker->unique()->word(),
        ];
    }
}
