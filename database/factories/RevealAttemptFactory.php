<?php

namespace Database\Factories;

use App\Enums\RevealSource;
use App\Models\RevealAttempt;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevealAttempt>
 */
class RevealAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'fact_topic' => fake()->words(3, true),
            'holder_name' => fake()->firstName(),
            'source' => RevealSource::InCharacter,
            'reason' => fake()->sentence(),
            'reviewed' => true,
            'approved' => false,
            'verdict' => fake()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(['approved' => true]);
    }
}
