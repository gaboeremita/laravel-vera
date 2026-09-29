<?php

namespace App\Listeners;

use App\Actions\Quests\SyncSessionQuests;
use App\Events\Quests\QuestEnded;
use App\Models\WorldSession;

/**
 * When a quest ends, quests that require it, and later runs of it, may now
 * be able to start.
 */
class UnlockQuests
{
    public function __construct(private readonly SyncSessionQuests $syncSessionQuests) {}

    public function handle(QuestEnded $event): void
    {
        $session = WorldSession::find($event->sessionId);
        if ($session !== null) {
            $this->syncSessionQuests->handle($session);
        }
    }
}
