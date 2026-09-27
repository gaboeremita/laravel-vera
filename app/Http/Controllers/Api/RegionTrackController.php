<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RegionTrackController extends Controller
{
    public function store(Request $request, World $world, Region $region): JsonResponse
    {
        Gate::authorize('update', $world);

        $validated = $request->validate([
            'track' => ['required', 'file', 'mimes:mp3,wav', 'max:20480'],
        ]);

        $file = $validated['track'];
        $previous = $region->track()->first();
        $previousPath = $previous?->path;
        $previousDisk = $previous?->disk;

        $path = $file->store("regions/{$region->id}/track", 'public');

        try {
            $track = $region->track()->updateOrCreate([], [
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

        return response()->json(['trackUrl' => $track->url], 201);
    }

    public function destroy(World $world, Region $region): JsonResponse
    {
        Gate::authorize('update', $world);

        $track = $region->track;

        if ($track === null) {
            return response()->json(['message' => 'Track not found.'], 404);
        }

        Storage::disk($track->disk)->delete($track->path);
        $track->delete();

        return response()->json(['message' => 'Track deleted.']);
    }
}
