<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssistantKind;
use App\Enums\AssistantPortraitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertWorldResidentRequest;
use App\Http\Resources\WorldResidentResource;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\World;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WorldResidentController extends Controller
{
    public function upsert(UpsertWorldResidentRequest $request, World $world, Assistant $assistant): JsonResponse
    {
        Gate::authorize('update', $world);
        abort_unless($assistant->users()->whereKey($request->user())->exists(), 404);
        abort_unless($assistant->portrait_type === AssistantPortraitType::Avatar3D && $assistant->vrm()->exists(), 422);

        if ($assistant->kind !== AssistantKind::WorldNpc) {
            $assistantUser = AssistantUser::where('assistant_id', $assistant->id)->where('user_id', $request->user()->id)->firstOrFail();
            abort_unless(
                (new LlmManager)->resolveModelForAssistantUser($assistantUser)?->supports_tools,
                422,
                'Assistants living in a world need a model that supports tool calling. Choose one in this assistant\'s settings.',
            );
        }

        $validated = $request->validated();

        $resident = $world->residents()->updateOrCreate(['assistant_id' => $assistant->id], [
            'position' => $validated['position'],
            'rotation' => $validated['rotation'] ?? null,
            'posture' => $validated['posture'] ?? 'standing',
            'behavior' => $validated['behavior'],
            'behavior_settings' => $validated['behaviorSettings'] ?? null,
            'opening_message' => $validated['openingMessage'] ?? null,
            'custom_prompt' => $validated['customPrompt'] ?? null,
            'zone_access' => isset($validated['zoneAccess'])
                ? ['tags' => array_values(array_map(trim(...), $validated['zoneAccess']['tags'] ?? [])), 'zones' => array_values($validated['zoneAccess']['zones'] ?? [])]
                : null,
        ]);

        $resident->load(['assistant.vrm', 'assistant.poses.animationFile']);

        return response()->json((new WorldResidentResource($resident))->resolve());
    }

    public function destroy(World $world, Assistant $assistant): JsonResponse
    {
        Gate::authorize('update', $world);
        $world->residents()->where('assistant_id', $assistant->id)->firstOrFail()->delete();

        return response()->json(status: 204);
    }
}
