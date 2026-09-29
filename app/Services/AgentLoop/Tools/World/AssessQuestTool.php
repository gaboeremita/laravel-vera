<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Enums\EndingStatus;
use App\Jobs\AssessQuestEnding;
use RuntimeException;

class AssessQuestTool extends CreatorQuestTool
{
    public function name(): string
    {
        return 'assess_quest';
    }

    public function description(): string
    {
        return 'Has the ending of a quest that ended written again, when the creator asks.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => ['quest' => $this->questParameter()], 'required' => ['quest']];
    }

    public function handle(array $arguments): array
    {
        $run = $this->latestRun($this->quest($arguments));
        if ($run === null || ! $run->status->hasEnded()) {
            throw new RuntimeException('That quest hasn\'t ended.');
        }

        $run->update(['ending_status' => EndingStatus::Pending]);
        AssessQuestEnding::dispatch($run->id);

        return ['status' => 'writing the ending'];
    }
}
