<?php

namespace Database\Factories;

use App\Models\ResidentActivity;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentActivity>
 */
class ResidentActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'world_resident_id' => WorldResident::factory(),
            'source' => 'requested',
            'verb' => 'go_to',
            'target' => 'studio',
            'activity' => null,
            'reason' => null,
            'zone_id' => null,
            'outcome' => null,
            'outcome_reason' => null,
            'finished_at' => null,
        ];
    }

    public function finished(): static
    {
        return $this->state(fn () => ['outcome' => 'completed', 'finished_at' => now()]);
    }

    public function failed(string $reason = 'there is no way to get there'): static
    {
        return $this->state(fn () => ['outcome' => 'failed', 'outcome_reason' => $reason, 'finished_at' => now()]);
    }
}
