<?php

namespace Database\Factories;

use App\Enums\WorldResidentBehavior;
use App\Models\Assistant;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldResident>
 */
class WorldResidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_id' => World::factory(),
            'assistant_id' => Assistant::factory(),
            'position' => ['x' => 0, 'y' => 0, 'z' => 0],
            'rotation' => ['x' => 0, 'y' => 0, 'z' => 0],
            'behavior' => WorldResidentBehavior::Stationary,
        ];
    }
}
