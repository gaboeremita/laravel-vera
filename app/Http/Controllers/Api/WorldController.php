<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorldRequest;
use App\Http\Requests\UpdateWorldRequest;
use App\Http\Resources\WorldResource;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorldController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(WorldResource::collection(request()->user()->worlds()->with(['cardImage', 'spawnRegion'])->withCount('regions')->latest()->get()));
    }

    public function store(StoreWorldRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $world = DB::transaction(fn () => $request->user()->worlds()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'assistant_context_prompt' => $validated['assistantContextPrompt'],
            'npc_context_prompt' => $validated['npcContextPrompt'],
        ]));

        return response()->json((new WorldResource($world))->resolve(), 201);
    }

    public function show(World $world): JsonResponse
    {
        Gate::authorize('view', $world);

        return response()->json((new WorldResource($world->load([
            'regions' => fn ($query) => $query->orderBy('id'),
            'regions.passageLinks',
            'spawnRegion',
            'residents.assistant.vrm',
            'residents.assistant.poses.animationFile',
            'cardImage',
            'portraitImage',
        ])))->resolve());
    }

    public function update(UpdateWorldRequest $request, World $world): JsonResponse
    {
        Gate::authorize('update', $world);

        $validated = $request->validated();
        $world->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'assistant_context_prompt' => $validated['assistantContextPrompt'],
            'npc_context_prompt' => $validated['npcContextPrompt'],
            'spawn_region_id' => $validated['spawnRegionId'] ?? null,
            'spawn_passage_id' => $validated['spawnPassageId'] ?? null,
            'narrator_model_id' => $validated['narratorModelId'] ?? null,
            'review_reveals' => $validated['reviewReveals'] ?? $world->review_reveals,
        ]);

        return $this->show($world->fresh());
    }

    public function destroy(World $world): JsonResponse
    {
        Gate::authorize('delete', $world);
        DB::transaction(fn () => $world->delete());

        return response()->json(status: 204);
    }
}
