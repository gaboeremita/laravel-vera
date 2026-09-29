<?php

namespace App\Events\Quests;

/**
 * The player's items or credits changed. Conditions reading it are checked against the session as it is now.
 */
class PlayerInventoryChanged extends QuestTriggerEvent
{
    /**
     * @param  ?string  $handover  what the player handed a resident, when that is what changed
     */
    public function __construct(int $sessionId, private readonly ?string $handover = null)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return ['has', 'credits', 'gaveTo', 'spentWith'];
    }

    public function cause(): ?string
    {
        return $this->handover;
    }
}
