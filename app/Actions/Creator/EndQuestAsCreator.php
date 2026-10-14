<?php

namespace App\Actions\Creator;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Actions\Quests\EndQuestRun;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use RuntimeException;

class EndQuestAsCreator
{
    public function __construct(
        private readonly EndQuestRun $endQuestRun,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
    ) {}

    public function handle(WorldSession $session, Quest $quest, QuestStatus $outcome): WorldSessionQuest
    {
        $run = $session->latestRunOf($quest);
        if ($run?->status !== QuestStatus::Active) {
            throw new RuntimeException('That quest isn\'t going on.');
        }

        $this->endQuestRun->handle($run, $outcome, ['by' => 'creator'], byCreator: true);
        $this->broadcastQuestRuns->handle($session->id, [$run->fresh('quest')]);

        return $run;
    }
}
