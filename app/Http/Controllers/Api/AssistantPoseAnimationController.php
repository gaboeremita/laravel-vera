<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssistantPoseAnimationController extends Controller
{
    public function store(Request $request, int $assistantId, int $poseId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        $pose = $assistant->poses()->findOrFail($poseId);

        $request->validate([
            'animation' => ['required', 'file', 'extensions:vrma,fbx', 'max:10240'],
        ]);

        $file = $request->file('animation');
        $previousPath = $pose->animationFile?->path;
        $previousDisk = $pose->animationFile?->disk;

        $path = $file->store("poses/{$assistant->id}/{$pose->id}", 'public');

        try {
            $animationFile = $pose->animationFile()->updateOrCreate([], [
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

        return response()->json(['animation_url' => $animationFile->url], 201);
    }

    public function destroy(Request $request, int $assistantId, int $poseId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        $pose = $assistant->poses()->findOrFail($poseId);

        $animationFile = $pose->animationFile;

        if (! $animationFile) {
            return response()->json(['message' => 'No pose animation file found.'], 404);
        }

        Storage::disk($animationFile->disk)->delete($animationFile->path);
        $animationFile->delete();

        return response()->json(['message' => 'Pose animation deleted']);
    }
}
