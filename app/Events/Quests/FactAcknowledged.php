<?php

namespace App\Events\Quests;

/**
 * A resident learned a fact from the player. Conditions reading it are checked against the session as it is now.
 */
class FactAcknowledged extends QuestTriggerEvent
{
    public function leaves(): array
    {
        return ['acknowledged'];
    }
}
