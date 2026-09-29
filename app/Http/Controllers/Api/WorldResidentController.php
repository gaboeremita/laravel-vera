<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quests\FindQuestReferences;
use App\Enums\AssistantKind;
use App\Enums\AssistantPortraitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertWorldResidentRequest;
use App\Http\Resources\WorldResidentResource;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Region;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorldResidentController extends Controller
{
    public function upsert(UpsertWorldResidentRequest $request, World $world, Region $region, Assistant $assistant): JsonResponse
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

        $elsewhere = $world->residents()->with('region')->where('assistant_id', $assistant->id)->where('region_id', '!=', $region->id)->first();
        if ($elsewhere !== null) {
            return response()->json(['message' => "{$assistant->name} is a resident of {$elsewhere->region->name}.", 'regionId' => $elsewhere->region_id, 'regionName' => $elsewhere->region->name], 409);
        }

        $validated = $request->validated();

        $resident = $world->residents()->updateOrCreate(['assistant_id' => $assistant->id], [
            'region_id' => $region->id,
            'position' => $validated['position'],
            'rotation' => $validated['rotation'] ?? null,
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

    /**
     * Makes the region the resident's region with the default placement.
     * Sessions that already exist keep her where she was.
     */
    public function move(World $world, Region $region, Assistant $assistant): JsonResponse
    {
        Gate::authorize('update', $world);
        $resident = $world->residents()->where('assistant_id', $assistant->id)->firstOrFail();

        DB::transaction(function () use ($world, $region, $resident): void {
            WorldSession::whereHas('worldUser', fn ($query) => $query->where('world_id', $world->id))
                ->whereDoesntHave('residentStates', fn ($query) => $query->where('world_resident_id', $resident->id))
                ->pluck('id')
                ->each(fn (int $sessionId) => WorldSessionResident::create([
                    'world_session_id' => $sessionId,
                    'world_resident_id' => $resident->id,
                    'region_id' => $resident->region_id,
                    'position' => $resident->position,
                    'rotation' => ['y' => $resident->rotation['y'] ?? 0],
                ]));

            $resident->update([
                'region_id' => $region->id,
                'position' => WorldResident::DEFAULT_POSITION,
                'rotation' => ['x' => 0, 'y' => 0, 'z' => 0],
                'behavior' => 'stationary',
                'behavior_settings' => null,
                'zone_access' => null,
            ]);
        });

        $resident->load(['assistant.vrm', 'assistant.poses.animationFile']);

        return response()->json((new WorldResidentResource($resident))->resolve());
    }

    public function destroy(World $world, Region $region, Assistant $assistant, FindQuestReferences $findQuestReferences): JsonResponse
    {
        Gate::authorize('update', $world);
        $resident = $region->residents()->where('assistant_id', $assistant->id)->firstOrFail();
        $findQuestReferences->ensureResidentUnused($resident);
        $resident->delete();

        return response()->json(status: 204);
    }
}
