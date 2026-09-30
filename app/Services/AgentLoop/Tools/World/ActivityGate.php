<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Models\ActivityTerms;
use App\Models\Inventory;
use App\Models\Region;
use App\Models\WorldResident;
use Illuminate\Support\Collection;

/**
 * Which activities a resident can use. Responses are the player's, so a
 * resident only meets the vendor rule: an activity whose vendor is in the
 * room with them is not theirs to use; they get it from the vendor instead.
 */
class ActivityGate
{
    /**
     * @param  array<int, int>  $residentIdsInRoom  the residents in the same room as them
     */
    public function __construct(
        private readonly Region $region,
        private readonly Inventory $actor,
        private readonly array $residentIdsInRoom = [],
    ) {}

    /** @var ?Collection<string, ActivityTerms> */
    private ?Collection $terms = null;

    public function canUse(string $objectId, string $activityId): bool
    {
        return $this->vendorOnDuty($this->terms()->get("{$objectId}:{$activityId}")) === null;
    }

    /**
     * @return array{allowed: bool, reason: ?string, narration: ?string}
     */
    public function use(string $objectId, string $activityId): array
    {
        $vendor = $this->vendorOnDuty($this->terms()->get("{$objectId}:{$activityId}"));
        if ($vendor !== null) {
            return ['allowed' => false, 'reason' => "{$vendor->assistant->name} is here and serves this; talk to them for what you want.", 'narration' => null];
        }

        return ['allowed' => true, 'reason' => null, 'narration' => null];
    }

    /**
     * @return Collection<string, ActivityTerms>
     */
    private function terms(): Collection
    {
        return $this->terms ??= $this->region->activityTerms()->with('vendor.assistant')->get()->keyBy(fn (ActivityTerms $terms) => "{$terms->object_id}:{$terms->activity_id}");
    }

    private function vendorOnDuty(?ActivityTerms $terms): ?WorldResident
    {
        $vendorId = $terms?->vendor_resident_id;
        if ($vendorId === null || $vendorId === $this->actor->world_resident_id || ! in_array($vendorId, $this->residentIdsInRoom, true)) {
            return null;
        }

        return $terms->vendor;
    }
}
