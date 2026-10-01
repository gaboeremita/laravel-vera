<?php

namespace Database\Factories;

use App\Enums\AiProviderFormat;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'url' => 'https://fake-llm.test/chat/completions',
            'api_key' => 'test-key',
            'config_schema' => [],
            'format' => AiProviderFormat::Generic,
        ];
    }

    public function anthropic(): static
    {
        return $this->state([
            'url' => 'https://fake-anthropic.test/v1',
            'format' => AiProviderFormat::Anthropic,
        ]);
    }
}
