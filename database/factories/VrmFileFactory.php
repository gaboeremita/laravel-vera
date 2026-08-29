<?php

namespace Database\Factories;

use App\Models\VrmFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VrmFile>
 */
class VrmFileFactory extends Factory
{
    protected $model = VrmFile::class;

    public function definition(): array
    {
        return [
            'path' => 'vrm/1/'.$this->faker->uuid().'.vrm',
            'disk' => 'public',
            'mime_type' => 'application/octet-stream',
            'size' => $this->faker->numberBetween(1_000_000, 30_000_000),
            'original_name' => $this->faker->word().'.vrm',
        ];
    }
}
