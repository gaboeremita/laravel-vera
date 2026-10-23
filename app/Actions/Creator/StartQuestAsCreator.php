<?php

namespace App\Actions\Creator;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Actions\Quests\StartQuestRun;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use RuntimeException;

/**
 * Starts a quest right now, whatever its requirements, with a fresh run when
 * the latest one has ended.
 */
class StartQuestAsCreator
{
    public function __construct(
        private readonly StartQuestRun $startQuestRun,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
    ) {}

    public function handle(WorldSession $session, Quest $quest): WorldSessionQuest
    {
        $run = $session->latestRunOf($quest);
        if ($run?->status === QuestStatus::Active) {
            throw new RuntimeException('That quest is already going on.');
        }
        if ($run === null || $run->status->hasEnded()) {
            $run = $session->questRuns()->create(['quest_id' => $quest->id, 'run' => ($run?->run ?? 0) + 1, 'status' => QuestStatus::Available, 'state' => WorldSessionQuest::EMPTY_STATE]);
        }

        $this->startQuestRun->handle($run, 'creator', byCreator: true);
        $this->broadcastQuestRuns->handle($session->id, [$run->fresh('quest')]);

        return $run;
    }
}
