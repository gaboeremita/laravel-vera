<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\ResolveInventory;
use App\Actions\UseActivity;
use App\Models\ActivityTerms;
use App\Models\Inventory;
use App\Models\Region;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Support\Collection;

/**
 * Applies activity terms to a resident: which activities she can use, and
 * what using one gives and needs. An activity whose vendor is in the room
 * with her is not hers to use; she gets it from the vendor instead.
 */
class ActivityGate
{
    /**
     * @param  array<int, int>  $residentIdsInRoom  the residents in the same room as her
     */
    public function __construct(
        private readonly WorldSession $session,
        private readonly Region $region,
        private readonly Inventory $actor,
        private readonly string $actorName,
        private readonly array $residentIdsInRoom = [],
    ) {}

    /** @var ?Collection<string, ActivityTerms> */
    private ?Collection $terms = null;

    /** @var array<string, bool> */
    private array $usable = [];

    public function canUse(string $objectId, string $activityId): bool
    {
        $key = "{$objectId}:{$activityId}";
        $terms = $this->terms()->get($key);

        return $this->usable[$key] ??= $terms === null
            || ($this->vendorOnDuty($terms) === null && app(UseActivity::class)->missing($terms, $this->actor, app(ResolveInventory::class)->forObject($this->session, $this->region, $objectId)) === null);
    }

    /**
     * @return array{allowed: bool, reason: ?string, narration: ?string, action: ?string}
     */
    public function use(string $objectId, string $activityId): array
    {
        $vendor = $this->vendorOnDuty($this->terms()->get("{$objectId}:{$activityId}"));
        if ($vendor !== null) {
            return ['allowed' => false, 'reason' => "{$vendor->assistant->name} is here and serves this; talk to them for what you want.", 'narration' => null, 'action' => null];
        }

        return app(UseActivity::class)->handle($this->session, $this->region, $objectId, $activityId, $this->actor, $this->actorName, null);
    }

    /**
     * @return Collection<string, ActivityTerms>
     */
    private function terms(): Collection
    {
        return $this->terms ??= $this->region->activityTerms()->with(['requiredItem', 'vendor.assistant'])->get()->keyBy(fn (ActivityTerms $terms) => "{$terms->object_id}:{$terms->activity_id}");
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
