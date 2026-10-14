<?php

namespace App\Actions\Creator;

use App\Actions\Activities\ActivityEffects;
use App\Actions\Activities\ActivityUse;
use App\Actions\ResolveInventory;
use App\Actions\UseActivity;
use App\Models\Inventory;
use App\Models\Region;
use App\Models\WorldSession;
use Illuminate\Support\Facades\DB;

/**
 * Uses one response of an object's activity as the creator directs: its
 * conditions and the narrator are skipped, and every effect applies except
 * those that take credits or items from the player.
 */
class UseActivityAsCreator
{
    private const COSTS = ['takeCredits', 'takeItems'];

    public function __construct(
        private readonly UseActivity $useActivity,
        private readonly ResolveInventory $resolveInventory,
        private readonly ActivityEffects $effects,
    ) {}

    /**
     * @return array{allowed: bool, use: ?ActivityUse}
     */
    public function handle(WorldSession $session, Region $region, string $objectId, string $activityId, int $responseIndex, Inventory $player): array
    {
        $responses = $this->useActivity->terms($region, $objectId, $activityId)?->responseList() ?? [];
        if ($responses === []) {
            return ['allowed' => true, 'use' => null];
        }
        $response = $responses[$responseIndex] ?? abort(404);

        $use = new ActivityUse($session, $region, $objectId, $activityId, $player, $this->resolveInventory->forObject($session, $region, $objectId), $player->displayName(), null);
        $effects = collect($response['effects'] ?? [])->reject(fn (array $effect) => in_array($effect['type'] ?? null, self::COSTS, true))->values()->all();
        DB::transaction(function () use ($effects, $use): void {
            foreach ($this->effects->resolve($effects) as [$effect, $config]) {
                $effect->apply($config, $use);
            }
        });

        return ['allowed' => true, 'use' => $use];
    }
}
