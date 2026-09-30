<?php

namespace App\Actions\Activities;

use App\Models\Inventory;
use App\Models\KnownFact;
use App\Models\Region;
use App\Models\WorldSession;
use App\Models\WorldSessionObject;

/**
 * One use of an object's activity by the player: who, where and with what,
 * and what the conditions and effects it ran produced along the way.
 */
class ActivityUse
{
    /** @var array<int, KnownFact> */
    public array $learnedFacts = [];

    public ?string $narration = null;

    public ?string $action = null;

    public bool $objectStateChanged = false;

    private ?WorldSessionObject $objectState = null;

    public function __construct(
        public readonly WorldSession $session,
        public readonly Region $region,
        public readonly string $objectId,
        public readonly string $activityId,
        public readonly Inventory $player,
        public readonly Inventory $object,
        public readonly string $playerName,
        public readonly ?string $attempt,
    ) {}

    public function objectName(): string
    {
        return $this->region->layoutObject($this->objectId)['name'] ?? $this->objectId;
    }

    public function objectDescription(): string
    {
        return $this->region->layoutObject($this->objectId)['description'] ?? '';
    }

    public function activityName(): string
    {
        return $this->region->objectActivities($this->objectId)[$this->activityId]['name'] ?? $this->activityId;
    }

    /**
     * What item and credit movements are recorded under.
     */
    public function reason(): string
    {
        return "{$this->objectName()}: {$this->activityName()}";
    }

    public function objectState(): WorldSessionObject
    {
        return $this->objectState ??= WorldSessionObject::firstOrNew(
            ['world_session_id' => $this->session->id, 'region_id' => $this->region->id, 'object_id' => $this->objectId],
            ['state' => []],
        );
    }

    public function setObjectState(string $key, mixed $value): void
    {
        $state = $this->objectState();
        $state->state = [...($state->state ?? []), $key => $value];
        $state->save();
        $this->objectStateChanged = true;
    }
}
