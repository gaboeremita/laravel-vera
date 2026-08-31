<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorldRequest;
use App\Http\Requests\UpdateWorldRequest;
use App\Http\Resources\WorldResource;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class WorldController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(WorldResource::collection(request()->user()->worlds()->latest()->get()));
    }

    public function store(StoreWorldRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $environment = $validated['environment'];

        $path = $environment->store("worlds/{$request->user()->id}", 'public');

        try {
            $world = DB::transaction(fn () => $request->user()->worlds()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'],
                'assistant_context_prompt' => $validated['assistantContextPrompt'],
                'npc_context_prompt' => $validated['npcContextPrompt'],
                'settings' => $validated['settings'] ?? null,
                'environment_disk' => 'public',
                'environment_path' => $path,
                'environment_original_name' => $environment->getClientOriginalName(),
            ]));
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return response()->json(new WorldResource($world), 201);
    }

    public function show(World $world): JsonResponse
    {
        Gate::authorize('view', $world);

        return response()->json((new WorldResource($world->load(['residents.assistant.vrm'])))->resolve());
    }

    public function update(UpdateWorldRequest $request, World $world): JsonResponse
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

        if (($validated['environment'] ?? null) instanceof UploadedFile) {
            $environment = $validated['environment'];
            $path = $environment->store("worlds/{$request->user()->id}", 'public');

            $previousEnvironment = [
                'disk' => $world->environment_disk,
                'path' => $world->environment_path,
            ];
            $attributes['environment_disk'] = 'public';
            $attributes['environment_path'] = $path;
            $attributes['environment_original_name'] = $environment->getClientOriginalName();
        }

        try {
            DB::transaction(fn () => $world->update($attributes));
        } catch (\Throwable $exception) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        if ($previousEnvironment !== null) {
            Storage::disk($previousEnvironment['disk'])->delete($previousEnvironment['path']);
        }

        return response()->json((new WorldResource($world->fresh()))->resolve());
    }

    public function destroy(World $world): JsonResponse
    {
        Gate::authorize('delete', $world);
        $world->delete();

        return response()->json(status: 204);
    }
}
