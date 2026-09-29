<?php

namespace App\Actions\Quests;

use App\Enums\QuestEventType;
use App\Models\QuestEvent;
use App\Models\WorldSessionQuest;

/**
 * The one way a run's log is written.
 */
class RecordQuestEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(WorldSessionQuest $run, QuestEventType $type, ?string $beat = null, array $payload = [], bool $byCreator = false): QuestEvent
    {
        return QuestEvent::create([
            'world_session_quest_id' => $run->id,
            'beat' => $beat,
            'type' => $type,
            'payload' => $payload,
            'by_creator' => $byCreator,
        ]);
    }
}
