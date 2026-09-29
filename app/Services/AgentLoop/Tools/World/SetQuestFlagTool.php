<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\RecordQuestEvent;
use App\Enums\QuestEventType;
use App\Events\Quests\QuestFlagChanged;
use RuntimeException;

class SetQuestFlagTool extends CreatorQuestTool
{
    public function name(): string
    {
        return 'set_quest_flag';
    }

    public function description(): string
    {
        return 'Sets or clears a flag of a quest\'s latest run, when the creator asks.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => ['quest' => $this->questParameter(), 'flag' => ['type' => 'string'], 'on' => ['type' => 'boolean']],
            'required' => ['quest', 'flag', 'on'],
        ];
    }

    public function handle(array $arguments): array
    {
        $run = $this->latestRun($this->quest($arguments));
        if ($run === null || ! $run->isOpen()) {
            throw new RuntimeException('That quest isn\'t available or going on.');
        }
        $flag = trim((string) ($arguments['flag'] ?? ''));
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $flag) !== 1) {
            throw new RuntimeException('Name the flag with letters and digits, starting with a letter.');
        }

        $flags = $run->state['flags'] ?? [];
        $on = (bool) ($arguments['on'] ?? true);
        if ($on) {
            $flags[$flag] = ['by' => null, 'reason' => 'set by the creator'];
        } else {
            unset($flags[$flag]);
        }
        $run->mergeState(['flags' => $flags]);
        $run->save();
        app(RecordQuestEvent::class)->handle($run, $on ? QuestEventType::FlagSet : QuestEventType::FlagCleared, payload: ['flag' => $flag], byCreator: true);

        QuestFlagChanged::dispatch($this->session->id);
        $this->broadcast($run);

        return ['status' => $on ? 'set' : 'cleared'];
    }
}
