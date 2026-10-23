<?php

namespace App\Actions\Creator;

use App\Actions\Quests\AnnounceZonesEntered;
use App\Enums\Posture;
use App\Events\Quests\PlayerEnteredRegion;
use App\Models\Region;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves the session, and the residents following the player, to a zone, a
 * passage's arrival point or the spawn in any region, as the creator directs.
 * Arriving counts as entering the region and the zones there.
 */
class TravelAsCreator
{
    private const FOLLOWER_SPACING = 0.8;

    public function __construct(private readonly AnnounceZonesEntered $announceZones) {}

    /**
     * @param  ?array<string, mixed>  $zone
     * @param  ?array<string, mixed>  $passage
     * @param  Collection<int, WorldResident>  $followers
     * @return array{regionId: int, position: array{x: float, y: float, z: float}, facing: float, landInZone: ?string, followers: array<int, array{position: array{x: float, y: float, z: float}}>}
     */
    public function handle(WorldSession $session, Region $region, ?array $zone, ?array $passage, Collection $followers): array
    {
        $arrival = $zone !== null ? self::zoneMiddle($zone) : $passage['arrival'];
        $facing = $zone !== null ? 0.0 : (float) $passage['facing'];
        $from = $session->position;
        $regionChanged = $session->region_id !== $region->id;

        $placements = [];
        DB::transaction(function () use ($session, $region, $arrival, $facing, $followers, &$placements): void {
            $session->update(['region_id' => $region->id, 'position' => $arrival, 'arrival_facing' => $facing]);

            foreach ($followers->values() as $index => $follower) {
                $position = $this->beside($arrival, $facing, $index);
                WorldSessionResident::updateOrCreate(
                    ['world_session_id' => $session->id, 'world_resident_id' => $follower->id],
                    ['region_id' => $region->id, 'position' => $position, 'rotation' => ['y' => $facing], 'spot_id' => null, 'activity_id' => null, 'posture' => Posture::Standing->value, 'exit_position' => null],
                );
                $placements[$follower->id] = ['position' => $position];
            }
        });

        if ($regionChanged) {
            PlayerEnteredRegion::dispatch($session->id, $region->id);
        }
        $this->announceZones->handle($session->id, $region, $regionChanged ? null : $from, $arrival);

        return ['regionId' => $region->id, 'position' => $arrival, 'facing' => $facing, 'landInZone' => $zone['id'] ?? null, 'followers' => $placements];
    }

    /**
     * The middle of the zone's outline at its floor.
     *
     * @param  array<string, mixed>  $zone
     * @return array{x: float, y: float, z: float}
     */
    public static function zoneMiddle(array $zone): array
    {
        $outline = $zone['outline'];

        return [
            'x' => round(array_sum(array_column($outline, 0)) / count($outline), 6),
            'y' => (float) $zone['minY'],
            'z' => round(array_sum(array_column($outline, 1)) / count($outline), 6),
        ];
    }

    /**
     * Followers stand to either side of the arrival point, one step further out each.
     *
     * @param  array{x: float, y: float, z: float}  $arrival
     * @return array{x: float, y: float, z: float}
     */
    private function beside(array $arrival, float $facing, int $index): array
    {
        $side = ($index % 2 === 0 ? 1 : -1) * self::FOLLOWER_SPACING * (intdiv($index, 2) + 1);

        return [
            'x' => round($arrival['x'] + cos($facing) * $side, 6),
            'y' => $arrival['y'],
            'z' => round($arrival['z'] - sin($facing) * $side, 6),
        ];
    }
}
