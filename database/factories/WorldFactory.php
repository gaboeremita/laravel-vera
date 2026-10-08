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

    /**
     * @param  array<int, string>  $names
     */
    public function withSentiments(array $names = ['romance', 'trust', 'liking']): static
    {
        return $this->state(fn () => [
            'sentiments' => collect($names)->map(fn (string $name) => ['name' => $name, 'description' => "-10 is no {$name} at all; 10 is all the {$name} there is."])->all(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->afterCreating(fn (World $world) => $world->users()->attach($user));
    }
}
