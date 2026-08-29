<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssistantVrmController extends Controller
{
    public function store(Request $request, int $id): JsonResponse
    {
        $assistant = $request->user()->assistants()->findOrFail($id);

        $request->validate([
            'vrm' => ['required', 'file', 'extensions:vrm', 'max:51200'],
        ]);

        $file = $request->file('vrm');
        $previousPath = $assistant->vrm?->path;
        $previousDisk = $assistant->vrm?->disk;

        $path = $file->store("vrm/{$assistant->id}", 'public');

        try {
            $vrmFile = $assistant->vrm()->updateOrCreate([], [
                'path' => $path,
                'disk' => 'public',
                'mime_type' => 'application/octet-stream',
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

        return response()->json(['vrm_url' => $vrmFile->url], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $assistant = $request->user()->assistants()->findOrFail($id);

        $vrm = $assistant->vrm;

        if (! $vrm) {
            return response()->json(['message' => 'No VRM file found.'], 404);
        }

        Storage::disk($vrm->disk)->delete($vrm->path);
        $vrm->delete();

        return response()->json(['message' => 'VRM file deleted']);
    }
}
