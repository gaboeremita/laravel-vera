<?php

namespace App\Events\Quests;

class PlayerEnteredZone extends QuestTriggerEvent
{
    public function __construct(int $sessionId, public readonly int $regionId, public readonly string $zoneId)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return ['enterZone'];
    }

    public function matches(string $leaf, mixed $value): bool
    {
        return $leaf === 'enterZone' && ($value['region'] ?? null) === $this->regionId && ($value['zone'] ?? null) === $this->zoneId;
    }
}
