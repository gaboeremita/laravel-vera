<?php

namespace App\Listeners;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Actions\Quests\EndQuestRun;
use App\Actions\Quests\LookUpOfferCondition;
use App\Actions\Quests\QuestConditions;
use App\Actions\Quests\QuestSessionState;
use App\Actions\Quests\RecordQuestEvent;
use App\Actions\Quests\StartQuestRun;
use App\Contracts\QuestTrigger;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestStarted;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Facades\Cache;

/**
 * Moves quests forward when something they depend on happens: latches what
 * the trigger matched, finishes beats, and starts, completes or fails runs.
 */
class AdvanceQuests
{
    private const LOCK_SECONDS = 30;

    private const LOCK_WAIT_SECONDS = 5;

    /**
     * Triggers raised while this process is already advancing a session,
     * by session id. They are handled after the current one, since waiting
     * for the lock this process holds would never end.
     *
     * @var array<int, array<int, QuestTrigger>>
     */
    private static array $pending = [];

    public function __construct(
        private readonly QuestConditions $conditions,
        private readonly RecordQuestEvent $recordQuestEvent,
        private readonly StartQuestRun $startQuestRun,
        private readonly EndQuestRun $endQuestRun,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
    ) {}

    public function handle(QuestTrigger $trigger): void
    {
        $sessionId = $trigger->sessionId();
        if (array_key_exists($sessionId, self::$pending)) {
            self::$pending[$sessionId][] = $trigger;

            return;
        }

        Cache::lock("quests:{$sessionId}", self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, function () use ($sessionId, $trigger): void {
            self::$pending[$sessionId] = [$trigger];
            try {
                while (self::$pending[$sessionId] !== []) {
                    $this->advance(array_shift(self::$pending[$sessionId]));
                }
            } finally {
                unset(self::$pending[$sessionId]);
            }
        });
    }

    private function advance(QuestTrigger $trigger): void
    {
        $session = WorldSession::find($trigger->sessionId());
        if ($session === null) {
            return;
        }

        $startedRunId = $trigger instanceof QuestStarted ? $trigger->runId : null;
        $runs = $session->questRuns()->with('quest')->whereIn('status', [QuestStatus::Available, QuestStatus::Active])
            ->when($startedRunId !== null, fn ($query) => $query->whereKey($startedRunId))
            ->get();
        if ($runs->isEmpty()) {
            return;
        }

        $state = QuestSessionState::for($session);
        $changed = [];
        $notices = [];

        foreach ($runs as $run) {
            $before = json_encode([$run->status, $run->state]);
            $notices = [...$notices, ...$this->progress($run, $trigger, $state)];
            if ($run->isDirty()) {
                $run->save();
            }
            if (json_encode([$run->status, $run->state]) !== $before) {
                $changed[] = $run;
            }
        }

        $this->broadcastQuestRuns->handle($session->id, $changed, $notices);
    }

    /**
     * @return array<int, array{type: string, questTitle: string, text: string}>
     */
    private function progress(WorldSessionQuest $run, QuestTrigger $trigger, QuestSessionState $state): array
    {
        $quest = $run->quest;
        $concerns = fn (?array $condition): bool => $trigger instanceof QuestStarted
            || array_intersect($this->conditions->leavesOf($condition), $trigger->leaves()) !== [];

        if ($run->status === QuestStatus::Available && $quest->startMode() === 'offer') {
            if ($concerns($quest->offerWhen())) {
                $this->conditions->latch($quest->offerWhen(), $run, $trigger, LookUpOfferCondition::SCOPE);
            }

            return [];
        }
        if ($run->status === QuestStatus::Available) {
            $when = $quest->definition['start']['when'] ?? null;
            if ($quest->startMode() !== 'condition' || ! $concerns($when)) {
                return [];
            }
            $this->conditions->latch($when, $run, $trigger, 'start');
            if (! $this->conditions->holds($when, $run, $state, 'start')) {
                return [];
            }
            $run->save();
            $this->startQuestRun->handle($run, 'condition', payload: $this->because($trigger));

            return [BroadcastQuestRuns::notice('questStarted', $run, $quest->description())];
        }

        foreach ($run->currentBeats() as $beat) {
            if ($concerns($beat['when'] ?? null)) {
                $this->conditions->latch($beat['when'] ?? null, $run, $trigger, "beat:{$beat['id']}");
            }
        }
        foreach (['fail', 'complete'] as $scope) {
            if ($concerns($quest->definition[$scope] ?? null)) {
                $this->conditions->latch($quest->definition[$scope] ?? null, $run, $trigger, $scope);
            }
        }

        $notices = $this->finishBeats($run, $trigger, $state);

        return [...$notices, ...$this->endIfDecided($run, $trigger, $state)];
    }

    /**
     * Finishes current beats whose condition holds, again and again, so a
     * beat whose requirements finish in this pass can finish in it too.
     *
     * @return array<int, array{type: string, questTitle: string, text: string}>
     */
    private function finishBeats(WorldSessionQuest $run, QuestTrigger $trigger, QuestSessionState $state): array
    {
        $notices = [];
        do {
            $finished = collect($run->currentBeats())
                ->filter(fn (array $beat) => $this->conditions->holds($beat['when'] ?? null, $run, $state, "beat:{$beat['id']}"))
                ->values();

            foreach ($finished as $beat) {
                $run->mergeState(['finishedBeats' => [...$run->finishedBeats(), $beat['id']]]);
                $this->recordQuestEvent->handle($run, QuestEventType::BeatFinished, $beat['id'], ['trigger' => class_basename($trigger), ...$this->because($trigger)]);
                $notices[] = BroadcastQuestRuns::notice('beatFinished', $run, $beat['text']);
            }
        } while ($finished->isNotEmpty());

        return $notices;
    }

    /**
     * Fails or completes the run. When both hold, the run fails, and the
     * ending is told completion held too.
     *
     * @return array<int, array{type: string, questTitle: string, text: string}>
     */
    private function endIfDecided(WorldSessionQuest $run, QuestTrigger $trigger, QuestSessionState $state): array
    {
        $quest = $run->quest;
        $complete = $quest->definition['complete'] ?? null;
        $completes = $complete === null
            ? count($run->finishedBeats()) >= count($quest->beats()) && $quest->beats() !== []
            : $this->conditions->holds($complete, $run, $state, 'complete');
        $fails = ($quest->definition['fail'] ?? null) !== null && $this->conditions->holds($quest->definition['fail'], $run, $state, 'fail');

        if (! $fails && ! $completes) {
            return [];
        }

        $run->save();
        $status = $fails ? QuestStatus::Failed : QuestStatus::Completed;
        $this->endQuestRun->handle($run, $status, ['trigger' => class_basename($trigger), 'completeAlsoHeld' => $fails && $completes, ...$this->because($trigger)]);

        return [BroadcastQuestRuns::notice('questEnded', $run, $status->value)];
    }

    /**
     * The trigger's own account of what happened, for the event it caused.
     *
     * @return array{because?: string}
     */
    private function because(QuestTrigger $trigger): array
    {
        $cause = $trigger->cause();

        return $cause === null ? [] : ['because' => $cause];
    }
}
