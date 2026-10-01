<?php

namespace Database\Factories;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => AiProvider::factory(),
            'name' => fake()->words(2, true),
            'endpoint' => fake()->slug(2),
            'config' => [],
            'supports_tools' => false,
            'cache_marks' => false,
            'conversation_key_field' => null,
        ];
    }

    public function cacheMarks(): static
    {
        return $this->state(['cache_marks' => true]);
    }

    public function conversationKeyField(string $field = 'session_id'): static
    {
        return $this->state(['conversation_key_field' => $field]);
    }
}
