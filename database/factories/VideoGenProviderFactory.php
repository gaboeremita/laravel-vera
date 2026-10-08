<?php

namespace Database\Factories;

use App\Enums\VideoGenProviderFormat;
use App\Models\User;
use App\Models\VideoGenProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoGenProvider>
 */
class VideoGenProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->company(),
            'url' => 'https://fake-video.test/api/v1/videos',
            'api_key' => 'test-key',
            'format' => VideoGenProviderFormat::OpenRouter,
        ];
    }
}
