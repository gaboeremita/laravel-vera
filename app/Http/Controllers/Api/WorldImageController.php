<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class WorldImageController extends Controller
{
    public function storeCard(Request $request, World $world): JsonResponse
    {
        return $this->store($request, $world, 'card');
    }

    public function destroyCard(World $world): JsonResponse
    {
        return $this->destroy($world, 'card');
    }

    public function storePortrait(Request $request, World $world): JsonResponse
    {
        return $this->store($request, $world, 'portrait');
    }

    public function destroyPortrait(World $world): JsonResponse
    {
        return $this->destroy($world, 'portrait');
    }

    private function store(Request $request, World $world, string $role): JsonResponse
    {
        Gate::authorize('update', $world);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'max:10480'],
        ]);

        $relation = $role === 'card' ? $world->cardImage() : $world->portraitImage();
        $file = $validated['image'];
        $previous = $relation->first();
        $previousPath = $previous?->path;
        $previousDisk = $previous?->disk;

        $path = $file->store("worlds/{$world->id}/{$role}", 'public');

        try {
            $image = $relation->updateOrCreate([], [
                'role' => $role,
                'path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        if ($previousPath) {
            Storage::disk($previousDisk)->delete($previousPath);
        }

        return response()->json(['image_url' => $image->url], 201);
    }

    private function destroy(World $world, string $role): JsonResponse
    {
        Gate::authorize('update', $world);

        $image = $role === 'card' ? $world->cardImage : $world->portraitImage;

        if ($image === null) {
            return response()->json(['message' => ucfirst($role).' image not found.'], 404);
        }

        Storage::disk($image->disk)->delete($image->path);
        $image->delete();

        return response()->json(['message' => ucfirst($role).' image deleted.']);
    }
}
