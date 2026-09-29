<?php

namespace Database\Factories;

use App\Models\ResidentFeeling;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentFeeling>
 */
class ResidentFeelingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'world_resident_id' => WorldResident::factory(),
            'romance' => 0,
            'trust' => 0,
            'liking' => 0,
        ];
    }
}
