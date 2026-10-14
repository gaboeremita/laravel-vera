<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Creator\SetQuestFlag;

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
        $on = (bool) ($arguments['on'] ?? true);
        app(SetQuestFlag::class)->handle($this->session, $this->quest($arguments), (string) ($arguments['flag'] ?? ''), $on);

        return ['status' => $on ? 'set' : 'cleared'];
    }
}
