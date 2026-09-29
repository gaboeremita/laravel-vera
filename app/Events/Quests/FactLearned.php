<?php

namespace App\Events\Quests;

/**
 * The player learned a fact. Conditions reading it are checked against the session as it is now.
 */
class FactLearned extends QuestTriggerEvent
{
    public function leaves(): array
    {
        return ['knows'];
    }
}
