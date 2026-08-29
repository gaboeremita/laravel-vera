<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssistantImageController extends Controller
{
    public function store(Request $request, int $id): JsonResponse
    {
        $assistant = $request->user()->assistants()->findOrFail($id);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'max:10480'],
        ]);

        $file = $validated['image'];
        $previousPath = $assistant->cardImage?->path;
        $previousDisk = $assistant->cardImage?->disk;

        $path = $file->store("card/{$assistant->id}", 'public');

        try {
            $image = $assistant->cardImage()->updateOrCreate([], [
                'path' => $path,
                'disk' => 'public',
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        if ($previousPath) {
            Storage::disk($previousDisk)->delete($previousPath);
        }

        return response()->json(['image_url' => $image->url], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $assistant = $request->user()->assistants()->findOrFail($id);

        $image = $assistant->cardImage;

        if (! $image) {
            return response()->json(['message' => 'No card image found.'], 404);
        }

        Storage::disk($image->disk)->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Card image deleted.']);
    }
}
