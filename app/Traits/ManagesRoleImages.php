<?php

namespace App\Traits;

use App\Models\Region;
use App\Models\World;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait ManagesRoleImages
{
    private function storeRoleImage(Request $request, World|Region $owner, string $role, string $directory): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'max:10480'],
        ]);

        $relation = $role === 'card' ? $owner->cardImage() : $owner->portraitImage();
        $file = $validated['image'];
        $previous = $relation->first();
        $previousPath = $previous?->path;
        $previousDisk = $previous?->disk;

        $path = $file->store("{$directory}/{$role}", 'public');

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

    private function destroyRoleImage(World|Region $owner, string $role): JsonResponse
    {
        $image = $role === 'card' ? $owner->cardImage : $owner->portraitImage;

        if ($image === null) {
            return response()->json(['message' => ucfirst($role).' image not found.'], 404);
        }

        Storage::disk($image->disk)->delete($image->path);
        $image->delete();

        return response()->json(['message' => ucfirst($role).' image deleted.']);
    }
}
