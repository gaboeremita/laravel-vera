<?php

namespace Database\Factories;

use App\Models\Sound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sound>
 */
class SoundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hash = hash('sha256', fake()->unique()->uuid());

        return [
            'hash' => $hash,
            'disk' => 'public',
            'path' => "sounds/{$hash}.mp3",
            'mime_type' => 'audio/mpeg',
            'size' => 2048,
            'original_name' => 'chime.mp3',
        ];
    }
}
