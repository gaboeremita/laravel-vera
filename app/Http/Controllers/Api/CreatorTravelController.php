<?php

namespace App\Http\Controllers\Api;

use App\Actions\Creator\TravelAsCreator;
use App\Actions\ResolveResidentRegion;
use App\Http\Controllers\Controller;
use App\Traits\ResolvesCreatorSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreatorTravelController extends Controller
{
    use ResolvesCreatorSession;

    public function store(Request $request, int $world, int $session, TravelAsCreator $travelAsCreator, ResolveResidentRegion $resolveResidentRegion): JsonResponse
    {
        $worldSession = $this->resolveCreatorSession($request, $world, $session);
        $validated = $request->validate([
            'regionId' => ['required_without:spawn', 'integer'],
            'zoneId' => ['nullable', 'string', 'max:100'],
            'passageId' => ['nullable', 'string', 'max:100'],
            'spawn' => ['sometimes', 'boolean'],
            'followerIds' => ['sometimes', 'array', 'max:20'],
            'followerIds.*' => ['integer', 'distinct'],
        ]);
        $worldModel = $worldSession->worldUser->world;

        if ($request->boolean('spawn')) {
            $region = $worldModel->spawnRegion ?? abort(404);
            $passage = $worldModel->spawnPassage() ?? abort(404);
            $zone = null;
        } else {
            $region = $worldModel->regions()->findOrFail($validated['regionId']);
            if (filled($validated['zoneId'] ?? null) === filled($validated['passageId'] ?? null)) {
                throw ValidationException::withMessages(['zoneId' => 'Name a zone or a passage.']);
            }
            $zone = filled($validated['zoneId'] ?? null) ? (collect($region->layout['zones'] ?? [])->firstWhere('id', $validated['zoneId']) ?? abort(404)) : null;
            $passage = filled($validated['passageId'] ?? null) ? ($region->passage($validated['passageId']) ?? abort(404)) : null;
        }

        $followerIds = $validated['followerIds'] ?? [];
        $followers = $worldModel->residents()->with('assistant')->findMany($followerIds);
        abort_if($followers->count() !== count($followerIds), 404);
        foreach ($followers as $follower) {
            if ($resolveResidentRegion->handle($worldSession, $follower)->id !== $worldSession->region_id) {
                throw ValidationException::withMessages(['followerIds' => "{$follower->assistant->name} is not in this region."]);
            }
        }

        return response()->json($travelAsCreator->handle($worldSession, $region, $zone, $passage, $followers));
    }
}
