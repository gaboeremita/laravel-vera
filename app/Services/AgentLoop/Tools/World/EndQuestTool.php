<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\EndQuestRun;
use App\Enums\QuestStatus;
use RuntimeException;

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
        $run = $this->latestRun($this->quest($arguments));
        if ($run?->status !== QuestStatus::Active) {
            throw new RuntimeException('That quest isn\'t going on.');
        }

        app(EndQuestRun::class)->handle($run, ($arguments['outcome'] ?? '') === 'failed' ? QuestStatus::Failed : QuestStatus::Completed, ['by' => 'creator'], byCreator: true);
        $this->broadcast($run);

        return ['status' => 'ended'];
    }
}
