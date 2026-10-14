<?php

namespace Database\Factories;

use App\Models\WorldSession;
use App\Models\WorldUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldSession>
 */
class WorldSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_user_id' => WorldUser::factory(),
            'title' => 'New session',
            'position' => null,
        ];
    }

    public function unlocked(): static
    {
        return $this->state(fn (array $attributes) => ['creator_mode_at' => now()]);
    }
}
