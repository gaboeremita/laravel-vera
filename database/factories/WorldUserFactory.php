<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\World;
use App\Models\WorldUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldUser>
 */
class WorldUserFactory extends Factory
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
            'user_id' => User::factory(),
        ];
    }
}
