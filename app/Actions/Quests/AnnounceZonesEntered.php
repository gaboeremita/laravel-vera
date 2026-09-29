<?php

namespace App\Actions\Quests;

use App\Actions\ResolveWorldState;
use App\Events\Quests\PlayerEnteredZone;
use App\Models\Region;

/**
 * Tells quests which zones the player just entered: every zone around the
 * new position that wasn't around the old one, so entering a room inside a
 * building enters the building too.
 */
class AnnounceZonesEntered
{
    public function __construct(private readonly ResolveWorldState $resolveWorldState) {}

    /**
     * @param  ?array{x: float, y: float, z: float}  $from  null when the player just arrived in the region
     * @param  array{x: float, y: float, z: float}  $to
     */
    public function handle(int $sessionId, Region $region, ?array $from, array $to): void
    {
        $layout = $region->layout ?? [];
        $before = $from === null ? [] : array_column($this->resolveWorldState->locate($layout, $from)['zoneChain'], 'id');

        foreach ($this->resolveWorldState->locate($layout, $to)['zoneChain'] as $zone) {
            if (! in_array($zone['id'], $before, true)) {
                PlayerEnteredZone::dispatch($sessionId, $region->id, $zone['id']);
            }
        }
    }
}
