<?php

namespace App\Events\Quests;

/**
 * How a resident feels about the player changed.
 */
class ResidentFeelingsChanged extends QuestTriggerEvent
{
    public function __construct(int $sessionId, private readonly string $cause)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return ['feeling'];
    }

    public function cause(): ?string
    {
        return $this->cause;
    }
}
