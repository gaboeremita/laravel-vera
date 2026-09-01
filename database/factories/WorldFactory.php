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
            'environment_disk' => 'public',
            'environment_path' => fake()->uuid().'.glb',
            'environment_original_name' => 'room.glb',
            'assistant_context_prompt' => fake()->sentence(),
            'npc_context_prompt' => fake()->sentence(),
            'settings' => ['player_spawn' => ['x' => 0, 'y' => 0, 'z' => 0]],
        ];
    }

    /**
     * Attach the given user to the world via the world_user pivot, replacing
     * the old direct user_id ownership (World::factory()->for($user) no
     * longer applies since World has no belongsTo(User) relation).
     */
    public function forUser(User $user): static
    {
        return $this->afterCreating(fn (World $world) => $world->users()->attach($user));
    }
}
