<?php

namespace App\Events\Quests;

/**
 * A run became active, or had beats undone. Every condition it watches is
 * checked, so a first beat about something the player already holds
 * finishes at once.
 */
class QuestStarted extends QuestTriggerEvent
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
