<?php

namespace Database\Factories;

use App\Models\Pose;
use App\Models\PoseAnimationFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PoseAnimationFile>
 */
class PoseAnimationFileFactory extends Factory
{
    protected $model = PoseAnimationFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pose_id' => Pose::factory(),
            'path' => 'poses/'.$this->faker->uuid().'.vrma',
            'disk' => 'public',
            'mime_type' => 'application/octet-stream',
            'size' => $this->faker->numberBetween(100_000, 5_000_000),
            'original_name' => $this->faker->word().'.vrma',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PoseAnimationFile $file) {
            if ($file->pose_id) {
                $file->path = "poses/{$file->pose_id}/".basename($file->path);
            }
        });
    }
}
