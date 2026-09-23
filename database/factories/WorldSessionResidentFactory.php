<?php

namespace Database\Factories;

use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldSessionResident>
 */
class WorldSessionResidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'world_resident_id' => WorldResident::factory(),
            'position' => ['x' => 0, 'y' => 0, 'z' => 0],
            'rotation' => ['y' => 0],
        ];
    }
}
