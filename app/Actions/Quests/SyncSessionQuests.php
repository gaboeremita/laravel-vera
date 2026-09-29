<?php

namespace App\Actions\Quests;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Gives a session a run of every quest that can start and has none open:
 * quests that start with the session become active on their first run, the
 * rest wait as available. Quests added to the world reach sessions in
 * progress the next time they're resumed, and ended quests unlock the ones
 * that require them.
 */
class SyncSessionQuests
{
    public function __construct(
        private readonly StartQuestRun $startQuestRun,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
    ) {}

    /**
     * @return array<int, WorldSessionQuest> the runs created
     */
    public function handle(WorldSession $session): array
    {
        $state = QuestSessionState::for($session);
        $created = [];
        $notices = [];

        foreach ($session->worldUser->world->quests()->get() as $quest) {
            // Starting a quest can end another and sync again before this loop moves on, so the snapshot may be behind.
            $latest = $session->questRuns()->where('quest_id', $quest->id)->orderByDesc('run')->first();
            if (! $this->canHaveNewRun($quest, $latest) || ! $state->requirementsMet($quest)) {
                continue;
            }

            $number = ($latest?->run ?? 0) + 1;
            try {
                $run = DB::transaction(fn () => $session->questRuns()->create(['quest_id' => $quest->id, 'run' => $number, 'status' => QuestStatus::Available, 'state' => WorldSessionQuest::EMPTY_STATE]));
            } catch (UniqueConstraintViolationException) {
                // Another request created this run first; the savepoint keeps any surrounding transaction usable.
                continue;
            }
            $run->setRelation('quest', $quest);
            $created[] = $run;

            if ($quest->startMode() === 'auto' && $number === 1) {
                $this->startQuestRun->handle($run, 'session');
                $notices[] = BroadcastQuestRuns::notice('questStarted', $run, $quest->description());
            } else {
                $notices[] = BroadcastQuestRuns::notice('questAvailable', $run, $quest->description());
            }
        }

        $this->broadcastQuestRuns->handle($session->id, $created, $notices);

        return $created;
    }

    /**
     * A later run of a repeatable quest only starts through a condition or
     * an offer, so a quest that starts with the session never restarts on
     * its own the moment it ends.
     */
    private function canHaveNewRun(Quest $quest, ?WorldSessionQuest $latest): bool
    {
        if ($latest === null) {
            return true;
        }

        return ! $latest->isOpen() && $quest->isRepeatable();
    }
}
