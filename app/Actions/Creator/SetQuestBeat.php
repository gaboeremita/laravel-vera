<?php

namespace App\Actions\Creator;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Actions\Quests\QuestConditions;
use App\Actions\Quests\RecordQuestEvent;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestStarted;
use App\Models\Quest;
use App\Models\WorldSession;
use RuntimeException;

/**
 * Finishes or undoes a beat of a quest in progress. Undoing a beat also
 * undoes the finished beats that come after it.
 */
class SetQuestBeat
{
    public function __construct(
        private readonly RecordQuestEvent $recordQuestEvent,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
    ) {}

    /**
     * @return array<int, string> the beats undone
     */
    public function handle(WorldSession $session, Quest $quest, string $beatId, bool $finished): array
    {
        $run = $session->latestRunOf($quest);
        if ($run?->status !== QuestStatus::Active) {
            throw new RuntimeException('That quest isn\'t going on.');
        }
        if ($quest->beat($beatId) === null) {
            throw new RuntimeException('That quest has no beat "'.$beatId.'". Its beats are: '.collect($quest->beats())->pluck('id')->implode(', ').'.');
        }

        if ($finished) {
            if (! $run->hasFinished($beatId)) {
                $run->mergeState(['finishedBeats' => [...$run->finishedBeats(), $beatId]]);
                $run->save();
                $this->recordQuestEvent->handle($run, QuestEventType::BeatFinished, $beatId, byCreator: true);
            }
            $undone = [];
        } else {
            $undone = array_values(array_intersect($this->withDependents($quest, $beatId), $run->finishedBeats()));
            $run->mergeState([
                'finishedBeats' => array_values(array_diff($run->finishedBeats(), $undone)),
                'seen' => array_values(array_filter($run->state['seen'] ?? [], fn (string $key) => ! in_array(QuestConditions::beatOfSeenKey($key), $undone, true))),
            ]);
            $run->save();
            foreach ($undone as $undoneBeat) {
                $this->recordQuestEvent->handle($run, QuestEventType::BeatUndone, $undoneBeat, byCreator: true);
            }
        }

        QuestStarted::dispatch($session->id, $run->id);
        $this->broadcastQuestRuns->handle($session->id, [$run->fresh('quest')]);

        return $undone;
    }

    /**
     * The beat and every beat that requires it, however indirectly.
     *
     * @return array<int, string>
     */
    private function withDependents(Quest $quest, string $beatId): array
    {
        $found = [$beatId];
        do {
            $before = count($found);
            foreach ($quest->beats() as $beat) {
                if (! in_array($beat['id'], $found, true) && array_intersect($beat['requires'] ?? [], $found) !== []) {
                    $found[] = $beat['id'];
                }
            }
        } while (count($found) > $before);

        return $found;
    }
}
