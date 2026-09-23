<?php

namespace App\Http\Controllers\Api;

use App\Actions\ResolveWorldState;
use App\Http\Controllers\Controller;
use App\Models\ResidentActivity;
use App\Traits\ResolvesWorldUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ResidentActivityController extends Controller
{
    use ResolvesWorldUser;

    public const VERBS = ['go_to', 'use', 'zone', 'follow', 'stop', 'stay', 'pose', 'invalid'];

    public const OUTCOMES = ['completed', 'failed', 'interrupted'];

    public function store(Request $request, int $world, int $session, int $resident, ResolveWorldState $resolveWorldState): JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);
        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldResident = $worldUser->world->residents()->findOrFail($resident);

        $validated = $request->validate([
            'verb' => ['required', Rule::in(self::VERBS)],
            'target' => ['nullable', 'string', 'max:100'],
            'activity' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
            'source' => ['sometimes', Rule::in(['requested', 'idle'])],
            'position' => ['nullable', 'array:x,y,z'],
            'position.x' => ['required_with:position', 'numeric'],
            'position.y' => ['required_with:position', 'numeric'],
            'position.z' => ['required_with:position', 'numeric'],
        ]);

        $zone = isset($validated['position'])
            ? $resolveWorldState->zoneAt($worldUser->world->layout ?? [], $validated['position'])
            : null;

        $activity = ResidentActivity::create([
            'world_session_id' => $worldSession->id,
            'world_resident_id' => $worldResident->id,
            'source' => $validated['source'] ?? 'requested',
            'verb' => $validated['verb'],
            'target' => $validated['target'] ?? null,
            'activity' => $validated['activity'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'zone_id' => $zone['id'] ?? null,
        ]);

        return response()->json(['id' => $activity->id], 201);
    }

    public function update(Request $request, int $world, int $session, int $resident, int $activity): Response|JsonResponse
    {
        $worldUser = $this->resolveWorldUser($request, $world);
        $worldSession = $worldUser->sessions()->findOrFail($session);
        $worldResident = $worldUser->world->residents()->findOrFail($resident);

        $residentActivity = ResidentActivity::where('world_session_id', $worldSession->id)
            ->where('world_resident_id', $worldResident->id)
            ->findOrFail($activity);

        $validated = $request->validate([
            'outcome' => ['required', Rule::in(self::OUTCOMES)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($residentActivity->outcome !== null) {
            return response()->json(['message' => 'This activity already has an outcome.'], 422);
        }

        $residentActivity->update([
            'outcome' => $validated['outcome'],
            'outcome_reason' => $validated['reason'] ?? null,
            'finished_at' => now(),
        ]);

        return response()->noContent();
    }
}
