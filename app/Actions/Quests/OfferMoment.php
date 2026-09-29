<?php

namespace App\Actions\Quests;

use App\Actions\ResolveWorldState;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Region;
use App\Models\WorldResident;
use App\Models\WorldSession;

/**
 * What only the current turn of a conversation with a giver knows: who the
 * giver is, the region they are in, and where everyone stands. Positions come
 * from the player's page with the message, since residents move there.
 */
class OfferMoment
{
    /** @var ?array<int, array<string, mixed>> */
    private ?array $giverZoneChain = null;

    /**
     * @param  ?array{user?: array{x: float, y: float, z: float}, residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     */
    public function __construct(
        public readonly WorldSession $session,
        public readonly WorldResident $giver,
        public readonly ?Region $region,
        public readonly ?array $positions,
    ) {}

    /**
     * Every message the player has sent the resident in this session,
     * whatever it says, the one being answered included.
     */
    public function messagesWith(WorldResident $resident): int
    {
        return Message::where('role', 'user')
            ->whereIn('conversation_id', Conversation::between($this->session->worldUser->user, $resident->assistant)
                ->where('world_session_id', $this->session->id)
                ->select('id'))
            ->count();
    }

    /**
     * The zone the giver stands in and the zones around it, outermost first;
     * empty when their position isn't known or they are in no zone.
     *
     * @return array<int, array<string, mixed>>
     */
    public function giverZoneChain(): array
    {
        return $this->giverZoneChain ??= $this->zoneChainOf($this->giver->id);
    }

    /**
     * The residents other than the giver whose position lies in the giver's
     * innermost zone, or in a zone inside it. Residents the page sent no
     * position for are elsewhere.
     *
     * @return array<int, int>
     */
    public function residentsInGiverZone(): array
    {
        $zone = collect($this->giverZoneChain())->last();
        if ($zone === null) {
            return [];
        }

        return collect($this->positions['residents'] ?? [])
            ->keys()
            ->map(fn (int|string $residentId) => (int) $residentId)
            ->reject(fn (int $residentId) => $residentId === $this->giver->id)
            ->filter(fn (int $residentId) => collect($this->zoneChainOf($residentId))->contains('id', $zone['id']))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function zoneChainOf(int $residentId): array
    {
        $point = $this->positions['residents'][$residentId] ?? null;
        if ($point === null || $this->region === null) {
            return [];
        }

        return app(ResolveWorldState::class)->locate($this->region->layout ?? [], $point)['zoneChain'];
    }
}
