<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\ResolveInventory;
use App\Actions\UseActivity;
use App\Models\ActivityTerms;
use App\Models\Inventory;
use App\Models\Region;
use App\Models\WorldSession;
use Illuminate\Support\Collection;

/**
 * Applies activity terms to a resident: which activities they can afford, and
 * what using one costs, gives and needs.
 */
class ActivityGate
{
    public function __construct(
        private readonly WorldSession $session,
        private readonly Region $region,
        private readonly Inventory $actor,
        private readonly string $actorName,
    ) {}

    /** @var ?Collection<string, ActivityTerms> */
    private ?Collection $terms = null;

    /** @var array<string, bool> */
    private array $affordable = [];

    public function canAfford(string $objectId, string $activityId): bool
    {
        $this->terms ??= $this->region->activityTerms()->with('requiredItem')->get()->keyBy(fn (ActivityTerms $terms) => "{$terms->object_id}:{$terms->activity_id}");
        $key = "{$objectId}:{$activityId}";
        $terms = $this->terms->get($key);

        return $this->affordable[$key] ??= $terms === null
            || app(UseActivity::class)->missing($terms, $this->actor, app(ResolveInventory::class)->forObject($this->session, $this->region, $objectId)) === null;
    }

    /**
     * @return array{allowed: bool, reason: ?string, narration: ?string, action: ?string}
     */
    public function use(string $objectId, string $activityId): array
    {
        return app(UseActivity::class)->handle($this->session, $this->region, $objectId, $activityId, $this->actor, $this->actorName, null);
    }
}
