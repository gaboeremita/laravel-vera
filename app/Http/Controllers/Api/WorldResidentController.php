<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssistantPortraitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertWorldResidentRequest;
use App\Http\Resources\WorldResidentResource;
use App\Models\Assistant;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WorldResidentController extends Controller
{
    public function upsert(UpsertWorldResidentRequest $request, World $world, Assistant $assistant): JsonResponse
    {
        Gate::authorize('update', $world);
        abort_unless($assistant->users()->whereKey($request->user())->exists(), 404);
        abort_unless($assistant->portrait_type === AssistantPortraitType::Avatar3D && $assistant->vrm()->exists(), 422);

        $validated = $request->validated();

        $resident = $world->residents()->updateOrCreate(['assistant_id' => $assistant->id], [
            'position' => $validated['position'],
            'rotation' => $validated['rotation'] ?? null,
            'behavior' => $validated['behavior'],
            'behavior_settings' => $validated['behaviorSettings'] ?? null,
            'opening_message' => $validated['openingMessage'] ?? null,
            'custom_prompt' => $validated['customPrompt'] ?? null,
        ]);

        $resident->load('assistant.vrm');

        return response()->json((new WorldResidentResource($resident))->resolve());
    }

    public function destroy(World $world, Assistant $assistant): JsonResponse
    {
        Gate::authorize('update', $world);
        $world->residents()->where('assistant_id', $assistant->id)->firstOrFail()->delete();

        return response()->json(status: 204);
    }
}
