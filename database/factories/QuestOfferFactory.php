<?php

namespace Database\Factories;

use App\Enums\QuestOfferStatus;
use App\Models\Conversation;
use App\Models\QuestOffer;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestOffer>
 */
class QuestOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'world_session_quest_id' => WorldSessionQuest::factory(),
            'conversation_id' => Conversation::factory(),
            'world_resident_id' => WorldResident::factory(),
            'status' => QuestOfferStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => QuestOfferStatus::Pending, 'answered_at' => null]);
    }
}
