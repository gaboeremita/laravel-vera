<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveInventory;
use App\Actions\UseActivity;
use App\Events\Quests\PlayerUsedActivity;
use App\Exceptions\NarratorUnavailable;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\KnownFact;
use App\Traits\ResolvesWorldSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityUseController extends Controller
{
    use ResolvesWorldSession;

    public function store(Request $request, int $world, int $session, ResolveInventory $resolveInventory, UseActivity $useActivity): JsonResponse
    {
        $validated = $request->validate([
            'regionId' => ['required', 'integer'],
            'objectId' => ['required', 'string'],
            'activityId' => ['required', 'string'],
            'attempt' => ['nullable', 'string', 'max:500'],
        ]);
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        $region = $worldSession->worldUser->world->regions()->findOrFail($validated['regionId']);
        abort_unless(array_key_exists($validated['activityId'], $region->objectActivities($validated['objectId'])), 404);

        $player = $resolveInventory->forPlayer($worldSession);
        $before = $player->summary();

        try {
            $outcome = $useActivity->handle($worldSession, $region, $validated['objectId'], $validated['activityId'], $player, $player->displayName(), $validated['attempt'] ?? null);
        } catch (NarratorUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $after = $player->summary();
        if ($outcome['allowed']) {
            PlayerUsedActivity::dispatch($worldSession->id, $region->id, $validated['objectId'], $validated['activityId']);
        }
        $use = $outcome['use'];

        return response()->json([
            'allowed' => $outcome['allowed'],
            'narration' => $outcome['narration'],
            'action' => $outcome['action'],
            'inventory' => $after,
            'changes' => Inventory::changesBetween($before, $after),
            'learnedFacts' => collect($use?->learnedFacts ?? [])->map(fn (KnownFact $known) => $known->toPayload())->values()->all(),
            'objectState' => $use?->objectStateChanged ? ['regionId' => $region->id, 'objectId' => $validated['objectId'], 'state' => $use->objectState()->state] : null,
        ]);
    }
}
