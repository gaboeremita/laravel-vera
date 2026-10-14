<?php

namespace App\Actions\Creator;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Actions\Quests\RecordQuestEvent;
use App\Actions\Quests\StartQuestRun;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use RuntimeException;

/**
 * Clears the latest run of a quest back to how it starts; its log keeps
 * everything that happened before.
 */
class ResetQuestAsCreator
{
    public function __construct(
        private readonly RecordQuestEvent $recordQuestEvent,
        private readonly StartQuestRun $startQuestRun,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
    ) {}

    public function handle(WorldSession $session, Quest $quest): WorldSessionQuest
    {
        $run = $session->latestRunOf($quest) ?? throw new RuntimeException('That quest has no run to reset.');

        $run->update(['status' => QuestStatus::Available, 'state' => WorldSessionQuest::EMPTY_STATE, 'ending' => null, 'ending_status' => null, 'started_at' => null, 'ended_at' => null]);
        $this->recordQuestEvent->handle($run, QuestEventType::Reset, byCreator: true);
        if ($quest->campaign_id !== null) {
            $session->campaignEndings()->where('campaign_id', $quest->campaign_id)->delete();
        }
        if ($quest->startMode() === 'auto') {
            $this->startQuestRun->handle($run, 'creator', byCreator: true);
        }
        $this->broadcastQuestRuns->handle($session->id, [$run->fresh('quest')]);

        return $run;
    }
}
