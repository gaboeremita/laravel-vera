<?php

namespace App\Actions\Creator;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Actions\Quests\RecordQuestEvent;
use App\Enums\QuestEventType;
use App\Events\Quests\QuestFlagChanged;
use App\Models\Quest;
use App\Models\WorldSession;
use RuntimeException;

class SetQuestFlag
{
    public function __construct(
        private readonly RecordQuestEvent $recordQuestEvent,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
    ) {}

    public function handle(WorldSession $session, Quest $quest, string $flag, bool $on): void
    {
        $run = $session->latestRunOf($quest);
        if ($run === null || ! $run->isOpen()) {
            throw new RuntimeException('That quest isn\'t available or going on.');
        }
        $flag = trim($flag);
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $flag) !== 1) {
            throw new RuntimeException('Name the flag with letters and digits, starting with a letter.');
        }

        $flags = $run->state['flags'] ?? [];
        if ($on) {
            $flags[$flag] = ['by' => null, 'reason' => 'set by the creator'];
        } else {
            unset($flags[$flag]);
        }
        $run->mergeState(['flags' => $flags]);
        $run->save();
        $this->recordQuestEvent->handle($run, $on ? QuestEventType::FlagSet : QuestEventType::FlagCleared, payload: ['flag' => $flag], byCreator: true);

        QuestFlagChanged::dispatch($session->id);
        $this->broadcastQuestRuns->handle($session->id, [$run->fresh('quest')]);
    }
}
