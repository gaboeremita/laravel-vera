<?php

namespace App\Actions;

use App\Actions\Quests\AnnounceZonesEntered;
use App\Enums\Posture;
use App\Events\Quests\PlayerEnteredRegion;
use App\Models\PassageLink;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TravelThroughPassage
{
    private const FOLLOWER_SPACING = 0.8;

    public function __construct(
        private readonly ResolveResidentRegion $resolveResidentRegion,
        private readonly AnnounceZonesEntered $announceZones,
    ) {}

    /**
     * Moves the session, and the residents following the player, to the
     * passage linked to the one the player walked into.
     *
     * @param  Collection<int, WorldResident>  $followers
     * @return array{regionId: int, position: array{x: float, y: float, z: float}, facing: float, followers: array<int, array{position: array{x: float, y: float, z: float}}>}
     *
     * @throws ValidationException
     */
    public function handle(WorldSession $session, int $regionId, string $passageId, Collection $followers): array
    {
        if ($session->region_id !== $regionId) {
            throw ValidationException::withMessages(['regionId' => 'The session is not in this region.']);
        }

        $link = PassageLink::with('targetRegion')->where('region_id', $regionId)->where('passage_id', $passageId)->first()
            ?? throw ValidationException::withMessages(['passageId' => 'This passage does not lead anywhere.']);
        $arrival = $link->targetRegion->passage($link->target_passage_id)
            ?? throw ValidationException::withMessages(['passageId' => 'The passage this one leads to no longer exists.']);

        foreach ($followers as $follower) {
            if ($this->resolveResidentRegion->handle($session, $follower)->id !== $regionId) {
                throw ValidationException::withMessages(['followerIds' => "{$follower->assistant->name} is not in this region."]);
            }
        }

        $placements = [];
        DB::transaction(function () use ($session, $link, $arrival, $followers, &$placements): void {
            $session->update(['region_id' => $link->target_region_id, 'position' => $arrival['arrival']]);

            foreach ($followers->values() as $index => $follower) {
                $position = $this->besideArrival($arrival, $index);
                WorldSessionResident::updateOrCreate(
                    ['world_session_id' => $session->id, 'world_resident_id' => $follower->id],
                    ['region_id' => $link->target_region_id, 'position' => $position, 'rotation' => ['y' => $arrival['facing']], 'spot_id' => null, 'activity_id' => null, 'posture' => Posture::Standing->value, 'exit_position' => null],
                );
                $placements[$follower->id] = ['position' => $position];
            }
        });

        PlayerEnteredRegion::dispatch($session->id, $link->target_region_id);
        $this->announceZones->handle($session->id, $link->targetRegion, null, $arrival['arrival']);

        return ['regionId' => $link->target_region_id, 'position' => $arrival['arrival'], 'facing' => $arrival['facing'], 'followers' => $placements];
    }

    /**
     * Followers stand to either side of the player, one step further out each.
     *
     * @param  array{arrival: array{x: float, y: float, z: float}, facing: float}  $passage
     * @return array{x: float, y: float, z: float}
     */
    private function besideArrival(array $passage, int $index): array
    {
        $side = ($index % 2 === 0 ? 1 : -1) * self::FOLLOWER_SPACING * (intdiv($index, 2) + 1);

        return [
            'x' => round($passage['arrival']['x'] + cos($passage['facing']) * $side, 6),
            'y' => $passage['arrival']['y'],
            'z' => round($passage['arrival']['z'] - sin($passage['facing']) * $side, 6),
        ];
    }
}
