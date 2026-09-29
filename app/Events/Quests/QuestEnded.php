<?php

namespace App\Events\Quests;

/**
 * A run completed, failed or was abandoned; quests that require it may now
 * become available.
 */
class QuestEnded extends QuestTriggerEvent
{
    public function __construct(int $sessionId, public readonly int $runId)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return [];
    }
}
