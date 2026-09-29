<?php

namespace Database\Factories;

use App\Models\Fact;
use App\Models\FactAcknowledgement;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FactAcknowledgement>
 */
class FactAcknowledgementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'fact_id' => Fact::factory(),
            'world_resident_id' => WorldResident::factory(),
        ];
    }
}
