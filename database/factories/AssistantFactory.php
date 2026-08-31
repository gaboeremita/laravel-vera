<?php

namespace Database\Factories;

use App\Enums\AssistantKind;
use App\Models\Assistant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assistant>
 */
class AssistantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'prompt' => [
                'identity' => [fake()->sentence()],
            ],
            'opening_message' => fake()->sentence(),
            'kind' => AssistantKind::Assistant,
        ];
    }
}
