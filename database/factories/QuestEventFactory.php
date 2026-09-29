<?php

namespace Database\Factories;

use App\Enums\QuestEventType;
use App\Models\QuestEvent;
use App\Models\WorldSessionQuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestEvent>
 */
class QuestEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_quest_id' => WorldSessionQuest::factory(),
            'beat' => null,
            'type' => QuestEventType::Started,
            'payload' => [],
            'by_creator' => false,
        ];
    }
}
