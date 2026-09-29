<?php

namespace Database\Factories;

use App\Enums\EndingStatus;
use App\Models\Campaign;
use App\Models\WorldSession;
use App\Models\WorldSessionCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldSessionCampaign>
 */
class WorldSessionCampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'campaign_id' => Campaign::factory(),
            'ending' => null,
            'ending_status' => EndingStatus::Pending,
        ];
    }
}
