<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Creator\SetQuestBeat;

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
        $undone = app(SetQuestBeat::class)->handle($this->session, $this->quest($arguments), (string) ($arguments['beat'] ?? ''), (bool) ($arguments['finished'] ?? true));

        return ['status' => 'done', 'undone' => $undone];
    }
}
