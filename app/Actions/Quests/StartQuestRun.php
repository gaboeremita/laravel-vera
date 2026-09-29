<?php

namespace App\Actions\Quests;

use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestStarted;
use App\Models\WorldSessionQuest;

/**
 * Makes a run active and has its conditions checked straight away.
 */
class StartQuestRun
{
    public function __construct(private readonly RecordQuestEvent $recordQuestEvent) {}

    /**
     * @param  string  $cause  what started it: session, condition, offer or creator
     */
    public function handle(WorldSessionQuest $run, string $cause, bool $byCreator = false): void
    {
        $run->update(['status' => QuestStatus::Active, 'started_at' => now()]);
        $this->recordQuestEvent->handle($run, QuestEventType::Started, payload: ['cause' => $cause], byCreator: $byCreator);

        QuestStarted::dispatch($run->world_session_id, $run->id);
    }
}
