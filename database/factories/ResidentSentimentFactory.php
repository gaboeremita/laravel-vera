<?php

namespace Database\Factories;

use App\Models\ResidentSentiment;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentSentiment>
 */
class ResidentSentimentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'world_resident_id' => WorldResident::factory(),
            'values' => [],
        ];
    }
}
