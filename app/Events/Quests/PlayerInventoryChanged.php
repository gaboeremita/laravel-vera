<?php

namespace App\Events\Quests;

/**
 * The player's items or credits changed. Conditions reading it are checked against the session as it is now.
 */
class PlayerInventoryChanged extends QuestTriggerEvent
{
    public function leaves(): array
    {
        return ['has', 'credits'];
    }
}
