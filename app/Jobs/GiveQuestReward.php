<?php

namespace App\Jobs;

use App\Actions\Quests\GiveQuestRewardAction;
use App\Models\WorldSessionQuest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pays a completed quest's reward once its ending is written, so the giver
 * can weigh how the user did. One payout per run at a time.
 */
class GiveQuestReward implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 180;

    public function __construct(public int $runId) {}

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function handle(GiveQuestRewardAction $giveQuestReward): void
    {
        $run = WorldSessionQuest::with(['quest', 'worldSession.worldUser.world'])->find($this->runId);
        if ($run === null) {
            return;
        }

        $giveQuestReward->handle($run);
    }
}
