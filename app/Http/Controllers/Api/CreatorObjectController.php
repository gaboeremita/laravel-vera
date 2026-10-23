<?php

namespace App\Http\Controllers\Api;

use App\Actions\Creator\UseActivityAsCreator;
use App\Actions\ResolveInventory;
use App\Events\Quests\PlayerUsedActivity;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\KnownFact;
use App\Models\Region;
use App\Models\WorldSession;
use App\Models\WorldSessionObject;
use App\Traits\ResolvesCreatorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorObjectController extends Controller
{
    use ResolvesCreatorSession;

    public function updatePassable(Request $request, int $world, int $session): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $validated = $request->validate(['regionId' => ['required', 'integer'], 'objectId' => ['required', 'string', 'max:100'], 'passable' => ['required', 'boolean']]);
        $region = $this->objectRegion($worldSession, $validated['regionId'], $validated['objectId']);

        $state = WorldSessionObject::firstOrNew(['world_session_id' => $worldSession->id, 'region_id' => $region->id, 'object_id' => $validated['objectId']], ['state' => []]);
        $state->state = [...($state->state ?? []), 'passable' => $validated['passable']];
        $state->save();

        return response()->json(['objectState' => ['regionId' => $region->id, 'objectId' => $validated['objectId'], 'state' => $state->state]]);
    }

    public function storeActivityUse(Request $request, int $world, int $session, ResolveInventory $resolveInventory, UseActivityAsCreator $useActivityAsCreator): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $validated = $request->validate([
            'regionId' => ['required', 'integer'],
            'objectId' => ['required', 'string', 'max:100'],
            'activityId' => ['required', 'string', 'max:100'],
            'response' => ['sometimes', 'integer', 'min:0'],
        ]);
        $region = $this->objectRegion($worldSession, $validated['regionId'], $validated['objectId']);
        abort_unless(array_key_exists($validated['activityId'], $region->objectActivities($validated['objectId'])), 404);

        $player = $resolveInventory->forPlayer($worldSession);
        $before = $player->summary();
        $outcome = $useActivityAsCreator->handle($worldSession, $region, $validated['objectId'], $validated['activityId'], $validated['response'] ?? 0, $player);
        $after = $player->fresh()->summary();
        PlayerUsedActivity::dispatch($worldSession->id, $region->id, $validated['objectId'], $validated['activityId']);
        $use = $outcome['use'];

        return response()->json([
            'allowed' => $outcome['allowed'],
            'narration' => null,
            'action' => null,
            'inventory' => $after,
            'changes' => Inventory::changesBetween($before, $after),
            'learnedFacts' => collect($use?->learnedFacts ?? [])->map(fn (KnownFact $known) => $known->toPayload())->values()->all(),
            'objectState' => $use?->objectStateChanged ? ['regionId' => $region->id, 'objectId' => $validated['objectId'], 'state' => $use->objectState()->state] : null,
        ]);
    }

    private function objectRegion(WorldSession $session, int $regionId, string $objectId): Region
    {
        $region = $session->worldUser->world->regions()->findOrFail($regionId);
        abort_if($region->layoutObject($objectId) === null, 404);

        return $region;
    }
}
