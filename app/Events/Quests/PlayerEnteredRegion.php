<?php

namespace App\Events\Quests;

class PlayerEnteredRegion extends QuestTriggerEvent
{
    public function __construct(int $sessionId, public readonly int $regionId)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return ['enterRegion'];
    }

    public function matches(string $leaf, mixed $value): bool
    {
        return $leaf === 'enterRegion' && $value === $this->regionId;
    }
}
