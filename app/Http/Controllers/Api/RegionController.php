<?php

namespace App\Http\Controllers\Api;

use App\Actions\ParseEnvironmentLayout;
use App\Actions\ReconcilePassages;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RegionController extends Controller
{
    public function store(StoreRegionRequest $request, World $world, ParseEnvironmentLayout $parseEnvironmentLayout): JsonResponse
    {
        Gate::authorize('update', $world);

        $validated = $request->validated();
        $environment = $validated['environment'];
        $parsed = $parseEnvironmentLayout->handle($environment->get());
        $path = $environment->store("worlds/{$request->user()->id}", 'public');

        try {
            $region = DB::transaction(fn () => $world->regions()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'],
                'assistant_context_prompt' => $validated['assistantContextPrompt'],
                'npc_context_prompt' => $validated['npcContextPrompt'],
                'settings' => $validated['settings'] ?? null,
                'environment_disk' => 'public',
                'environment_path' => $path,
                'environment_original_name' => $environment->getClientOriginalName(),
                'layout' => $parsed['layout'],
            ]));
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return response()->json([...(new RegionResource($region))->resolve(), 'layoutWarnings' => $parsed['warnings']], 201);
    }

    public function show(World $world, Region $region): JsonResponse
    {
        Gate::authorize('view', $world);

        return response()->json((new RegionResource($region->load(['cardImage', 'portraitImage', 'track', 'passageLinks.targetRegion', 'activityTerms.requiredItem.cardImage'])))->resolve());
    }

    public function update(UpdateRegionRequest $request, World $world, Region $region, ParseEnvironmentLayout $parseEnvironmentLayout, ReconcilePassages $reconcilePassages): JsonResponse
    {
        Gate::authorize('update', $world);

        $validated = $request->validated();
        $attributes = [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'assistant_context_prompt' => $validated['assistantContextPrompt'],
            'npc_context_prompt' => $validated['npcContextPrompt'],
            'settings' => $validated['settings'] ?? null,
        ];
        $previousEnvironment = null;
        $layoutWarnings = [];

        if (($validated['environment'] ?? null) instanceof UploadedFile) {
            $environment = $validated['environment'];
            $path = $environment->store("worlds/{$request->user()->id}", 'public');
            $previousEnvironment = [
                'disk' => $region->environment_disk,
                'path' => $region->environment_path,
            ];
            $attributes['environment_disk'] = 'public';
            $attributes['environment_path'] = $path;
            $attributes['environment_original_name'] = $environment->getClientOriginalName();
            $parsed = $parseEnvironmentLayout->handle($environment->get());
            $attributes['layout'] = $parsed['layout'];
            $layoutWarnings = $parsed['warnings'];
        }

        try {
            $removedLinks = DB::transaction(function () use ($region, $attributes, $reconcilePassages): array {
                $region->update($attributes);

                return $reconcilePassages->handle($region);
            });
        } catch (\Throwable $exception) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        if ($previousEnvironment !== null) {
            Storage::disk($previousEnvironment['disk'])->delete($previousEnvironment['path']);
        }

        return response()->json([
            ...(new RegionResource($region->fresh(['cardImage', 'portraitImage', 'track', 'passageLinks.targetRegion'])))->resolve(),
            'layoutWarnings' => $layoutWarnings,
            'removedLinks' => $removedLinks,
        ]);
    }

    public function destroy(World $world, Region $region): JsonResponse
    {
        Gate::authorize('update', $world);

        DB::transaction(function () use ($world, $region): void {
            if ($world->spawn_region_id === $region->id) {
                $world->update(['spawn_region_id' => null, 'spawn_passage_id' => null]);
            }
            $region->delete();
        });

        return response()->json(status: 204);
    }
}
