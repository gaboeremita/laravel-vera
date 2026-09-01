<?php

namespace Database\Factories;

use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'tracks/'.fake()->uuid().'.mp3',
            'disk' => 'public',
            'mime_type' => 'audio/mpeg',
            'size' => fake()->numberBetween(500_000, 5_000_000),
            'original_name' => 'song.mp3',
        ];
    }
}
