<?php

namespace Database\Factories;

use App\Models\VideoGenModel;
use App\Models\VideoGenProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoGenModel>
 */
class VideoGenModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => VideoGenProvider::factory(),
            'name' => 'Test Video Model',
            'endpoint' => 'test/video-model',
            'config' => [
                'duration' => 5,
                'aspect_ratio' => '16:9',
                'generate_audio' => false,
                'timeout' => 600,
            ],
        ];
    }
}
