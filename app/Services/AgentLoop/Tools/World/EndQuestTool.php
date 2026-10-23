<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Creator\EndQuestAsCreator;
use App\Enums\QuestStatus;

class EndQuestTool extends CreatorQuestTool
{
    public function name(): string
    {
        return 'end_quest';
    }

    public function description(): string
    {
        return 'Completes or fails a quest in progress, when the creator asks. Its ending is then written.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => ['quest' => $this->questParameter(), 'outcome' => ['type' => 'string', 'enum' => ['completed', 'failed']]],
            'required' => ['quest', 'outcome'],
        ];
    }

    public function handle(array $arguments): array
    {
        app(EndQuestAsCreator::class)->handle($this->session, $this->quest($arguments), ($arguments['outcome'] ?? '') === 'failed' ? QuestStatus::Failed : QuestStatus::Completed);

        return ['status' => 'ended'];
    }
}
