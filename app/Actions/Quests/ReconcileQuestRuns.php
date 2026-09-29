<?php

namespace App\Actions\Quests;

use App\Enums\QuestEventType;
use App\Models\Quest;
use App\Models\WorldSessionQuest;

/**
 * After a quest's definition changes, open runs forget beats that no longer
 * exist; beats that still exist keep their progress.
 */
class ReconcileQuestRuns
{
    public function __construct(private readonly RecordQuestEvent $recordQuestEvent) {}

    public function handle(Quest $quest): void
    {
        $beatIds = collect($quest->beats())->pluck('id')->all();

        $quest->runs()->whereIn('status', ['available', 'active'])->get()->each(function (WorldSessionQuest $run) use ($beatIds): void {
            $removed = array_values(array_diff($run->finishedBeats(), $beatIds));
            $seen = array_values(array_filter($run->state['seen'] ?? [], fn (string $key) => ! str_starts_with($key, 'beat:') || in_array(QuestConditions::beatOfSeenKey($key), $beatIds, true)));

            if ($removed === [] && count($seen) === count($run->state['seen'] ?? [])) {
                return;
            }

            $run->mergeState(['finishedBeats' => array_values(array_intersect($run->finishedBeats(), $beatIds)), 'seen' => $seen]);
            $run->save();
            $this->recordQuestEvent->handle($run, QuestEventType::DefinitionEdited, payload: ['removedBeats' => $removed]);
        });
    }
}
