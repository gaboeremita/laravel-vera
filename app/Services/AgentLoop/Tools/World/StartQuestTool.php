<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Creator\StartQuestAsCreator;

class StartQuestTool extends CreatorQuestTool
{
    public function name(): string
    {
        return 'start_quest';
    }

    public function description(): string
    {
        return 'Starts a quest for the user right now, whatever its requirements, when the creator asks.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => ['quest' => $this->questParameter()], 'required' => ['quest']];
    }

    public function handle(array $arguments): array
    {
        app(StartQuestAsCreator::class)->handle($this->session, $this->quest($arguments));

        return ['status' => 'started'];
    }
}
