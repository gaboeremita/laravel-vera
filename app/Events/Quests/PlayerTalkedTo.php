<?php

namespace App\Events\Quests;

class PlayerTalkedTo extends QuestTriggerEvent
{
    public function __construct(int $sessionId, public readonly int $residentId)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return ['talkTo'];
    }

    public function matches(string $leaf, mixed $value): bool
    {
        return $leaf === 'talkTo' && $value === $this->residentId;
    }
}
