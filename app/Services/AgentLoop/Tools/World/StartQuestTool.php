<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\StartQuestRun;
use App\Enums\QuestStatus;
use App\Models\WorldSessionQuest;
use RuntimeException;

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
        $quest = $this->quest($arguments);
        $run = $this->latestRun($quest);
        if ($run?->status === QuestStatus::Active) {
            throw new RuntimeException('That quest is already going on.');
        }
        if ($run === null || $run->status->hasEnded()) {
            $run = $this->session->questRuns()->create(['quest_id' => $quest->id, 'run' => ($run?->run ?? 0) + 1, 'status' => QuestStatus::Available, 'state' => WorldSessionQuest::EMPTY_STATE]);
        }

        app(StartQuestRun::class)->handle($run, 'creator', byCreator: true);
        $this->broadcast($run);

        return ['status' => 'started'];
    }
}
