<?php

namespace App\Events\Quests;

/**
 * How a resident feels about the player changed.
 */
class ResidentSentimentsChanged extends QuestTriggerEvent
{
    public function __construct(int $sessionId, private readonly string $cause)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return ['sentiment'];
    }

    public function cause(): ?string
    {
        return $this->cause;
    }
}
