<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Creator\ResetQuestAsCreator;

class ResetQuestTool extends CreatorQuestTool
{
    public function name(): string
    {
        return 'reset_quest';
    }

    public function description(): string
    {
        return 'Clears the latest run of a quest back to how it starts, when the creator asks. Its log keeps everything that happened before.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => ['quest' => $this->questParameter()], 'required' => ['quest']];
    }

    public function handle(array $arguments): array
    {
        app(ResetQuestAsCreator::class)->handle($this->session, $this->quest($arguments));

        return ['status' => 'reset'];
    }
}
