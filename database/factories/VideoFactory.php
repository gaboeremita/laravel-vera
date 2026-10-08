<?php

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Models\Message;
use App\Models\Video;
use App\Models\VideoGenModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'videoable_type' => (new Message)->getMorphClass(),
            'videoable_id' => Message::factory()->state(['role' => 'assistant']),
            'video_gen_model_id' => VideoGenModel::factory(),
            'status' => VideoStatus::Queued,
            'prompt' => fake()->sentence(),
            'duration' => 5,
            'aspect_ratio' => '16:9',
            'generate_audio' => false,
        ];
    }

    public function generating(): static
    {
        return $this->state(['status' => VideoStatus::Generating, 'job_id' => 'job-'.fake()->uuid()]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => VideoStatus::Completed,
            'job_id' => 'job-'.fake()->uuid(),
            'path' => 'messages/1/1/'.fake()->uuid().'.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024,
        ]);
    }

    public function failed(string $reason = 'Provider error'): static
    {
        return $this->state(['status' => VideoStatus::Failed, 'failure_reason' => $reason]);
    }
}
