<?php

namespace App\Events\Quests;

/**
 * A flag changed: granted, set by the creator, or written as an ending's resulting flag. Conditions reading it are checked against the session as it is now.
 */
class QuestFlagChanged extends QuestTriggerEvent
{
    public function leaves(): array
    {
        return ['flag'];
    }
}
