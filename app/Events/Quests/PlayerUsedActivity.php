<?php

namespace App\Events\Quests;

class PlayerUsedActivity extends QuestTriggerEvent
{
    public function __construct(int $sessionId, public readonly int $regionId, public readonly string $objectId, public readonly string $activityId)
    {
        parent::__construct($sessionId);
    }

    public function leaves(): array
    {
        return ['use'];
    }

    public function matches(string $leaf, mixed $value): bool
    {
        return $leaf === 'use'
            && ($value['region'] ?? null) === $this->regionId
            && ($value['object'] ?? null) === $this->objectId
            && ($value['activity'] ?? null) === $this->activityId;
    }
}
