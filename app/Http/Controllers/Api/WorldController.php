<?php

namespace App\Http\Controllers\Api;

use App\Actions\RenameSentiment;
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
            'sentiments' => $this->sentiments($validated['sentiments'] ?? []),
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
        DB::transaction(function () use ($world, $validated) {
            if (isset($validated['sentiments'])) {
                $this->renameSentiments($world, $validated['sentiments']);
            }
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
                'sentiments' => isset($validated['sentiments']) ? $this->sentiments($validated['sentiments']) : $world->sentiments,
            ]);
        });

        return $this->show($world->fresh());
    }

    /**
     * @param  array<int, array{name: string, description: string}>  $sentiments
     * @return array<int, array{name: string, description: string}>
     */
    private function sentiments(array $sentiments): array
    {
        return collect($sentiments)->map(fn (array $sentiment) => ['name' => trim($sentiment['name']), 'description' => trim($sentiment['description'])])->values()->all();
    }

    /**
     * Carries each renamed sentiment over to the scores and conditions that
     * name it, through a temporary name first so two sentiments can swap.
     *
     * @param  array<int, array{name: string, renamedFrom?: ?string}>  $sentiments
     */
    private function renameSentiments(World $world, array $sentiments): void
    {
        $renames = collect($sentiments)
            ->filter(fn (array $sentiment) => in_array($sentiment['renamedFrom'] ?? null, $world->sentimentNames(), true) && $sentiment['renamedFrom'] !== trim($sentiment['name']))
            ->mapWithKeys(fn (array $sentiment) => [$sentiment['renamedFrom'] => trim($sentiment['name'])]);
        $renameSentiment = app(RenameSentiment::class);

        $renames->keys()->each(fn (string $from, int $index) => $renameSentiment->handle($world, $from, "__rename{$index}__"));
        $renames->values()->each(fn (string $to, int $index) => $renameSentiment->handle($world, "__rename{$index}__", $to));
    }

    public function destroy(World $world): JsonResponse
    {
        Gate::authorize('delete', $world);
        DB::transaction(fn () => $world->delete());

        return response()->json(status: 204);
    }
}
