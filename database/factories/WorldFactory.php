<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<World>
 */
class WorldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'assistant_context_prompt' => fake()->sentence(),
            'npc_context_prompt' => fake()->sentence(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->afterCreating(fn (World $world) => $world->users()->attach($user));
    }
}
