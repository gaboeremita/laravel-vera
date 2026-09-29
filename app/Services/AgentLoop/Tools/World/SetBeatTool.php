<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\QuestConditions;
use App\Actions\Quests\RecordQuestEvent;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestStarted;
use App\Models\Quest;
use RuntimeException;

class SetBeatTool extends CreatorQuestTool
{
    public function name(): string
    {
        return 'set_beat';
    }

    public function description(): string
    {
        return 'Finishes or undoes a beat of a quest in progress, when the creator asks. Undoing a beat also undoes the finished beats that come after it.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => ['quest' => $this->questParameter(), 'beat' => ['type' => 'string', 'description' => 'The beat\'s id.'], 'finished' => ['type' => 'boolean']],
            'required' => ['quest', 'beat', 'finished'],
        ];
    }

    public function handle(array $arguments): array
    {
        $quest = $this->quest($arguments);
        $run = $this->latestRun($quest);
        if ($run?->status !== QuestStatus::Active) {
            throw new RuntimeException('That quest isn\'t going on.');
        }
        $beatId = (string) ($arguments['beat'] ?? '');
        if ($quest->beat($beatId) === null) {
            throw new RuntimeException('That quest has no beat "'.$beatId.'". Its beats are: '.collect($quest->beats())->pluck('id')->implode(', ').'.');
        }

        $record = app(RecordQuestEvent::class);
        if ((bool) ($arguments['finished'] ?? true)) {
            if (! $run->hasFinished($beatId)) {
                $run->mergeState(['finishedBeats' => [...$run->finishedBeats(), $beatId]]);
                $run->save();
                $record->handle($run, QuestEventType::BeatFinished, $beatId, byCreator: true);
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
                $record->handle($run, QuestEventType::BeatUndone, $undoneBeat, byCreator: true);
            }
        }

        QuestStarted::dispatch($this->session->id, $run->id);
        $this->broadcast($run);

        return ['status' => 'done', 'undone' => $undone];
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
