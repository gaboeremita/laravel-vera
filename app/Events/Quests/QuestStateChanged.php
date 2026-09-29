<?php

namespace App\Events\Quests;

/**
 * A quest was offered, accepted, declined, left unanswered, started or ended;
 * conditions about where a quest stands, or how often it was turned down,
 * are checked again.
 */
class QuestStateChanged extends QuestTriggerEvent
{
    public function __construct(int $sessionId, private readonly ?string $cause = null)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return ['questState', 'declinedTimes'];
    }

    public function cause(): ?string
    {
        return $this->cause;
    }
}
