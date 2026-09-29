<?php

namespace Database\Factories;

use App\Enums\EndingStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldSessionQuest>
 */
class WorldSessionQuestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'world_session_id' => WorldSession::factory(),
            'quest_id' => Quest::factory(),
            'run' => 1,
            'status' => QuestStatus::Available,
            'state' => WorldSessionQuest::EMPTY_STATE,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => QuestStatus::Active, 'started_at' => now()]);
    }

    public function ended(QuestStatus $status = QuestStatus::Completed): static
    {
        return $this->state(['status' => $status, 'started_at' => now()->subHour(), 'ended_at' => now(), 'ending_status' => EndingStatus::Pending]);
    }

    /**
     * @param  array<string, mixed>  $ending
     */
    public function withEnding(array $ending = []): static
    {
        return $this->ended()->state(['ending_status' => EndingStatus::Written, 'ending' => [
            'tier' => null,
            'title' => 'How it ended',
            'epilogue' => 'It ended.',
            'scores' => [['dimension' => 'care', 'score' => 7, 'reason' => 'Careful enough.']],
            'resultingFlags' => [],
            ...$ending,
        ]]);
    }
}
