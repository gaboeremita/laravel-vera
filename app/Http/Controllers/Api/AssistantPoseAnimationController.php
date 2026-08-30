<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssistantPortraitType;
use App\Http\Controllers\Controller;
use App\Models\Pose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssistantPoseAnimationController extends Controller
{
    public function store(Request $request, int $assistantId, int $poseId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        if ($assistant->portrait_type !== AssistantPortraitType::Avatar3D) {
            return response()->json([
                'message' => 'Poses are only available for 3D avatar assistants.',
            ], 422);
        }

        $pose = $assistant->poses()->findOrFail($poseId);

        return $this->storeAnimation($request, $pose);
    }

    /**
     * Uploads (creating the default pose first if it doesn't exist yet) the
     * assistant's default pose animation — see AssistantPoseController::updateDefault.
     */
    public function storeDefault(Request $request, int $assistantId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        if ($assistant->portrait_type !== AssistantPortraitType::Avatar3D) {
            return response()->json([
                'message' => 'Poses are only available for 3D avatar assistants.',
            ], 422);
        }

        $pose = $assistant->poses()->firstOrCreate(['name' => 'default']);

        return $this->storeAnimation($request, $pose);
    }

    public function destroy(Request $request, int $assistantId, int $poseId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        $pose = $assistant->poses()->findOrFail($poseId);

        return $this->destroyAnimation($pose);
    }

    public function destroyDefault(Request $request, int $assistantId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        $pose = $assistant->poses()->where('name', 'default')->first();

        if (! $pose) {
            return response()->json(['message' => 'No pose animation file found.'], 404);
        }

        return $this->destroyAnimation($pose);
    }

    private function storeAnimation(Request $request, Pose $pose): JsonResponse
    {
        $request->validate([
            'animation' => ['required', 'file', 'extensions:vrma,fbx', 'max:10240'],
        ]);

        $file = $request->file('animation');
        $previousPath = $pose->animationFile?->path;
        $previousDisk = $pose->animationFile?->disk;

        // store()'s auto-generated filename guesses the extension from MIME
        // sniffing, not the uploaded file's actual extension — a .vrma file
        // (a glTF-binary container) gets misdetected and saved as .glb. Since
        // playback branches on the stored file's extension to pick a loader,
        // the extension must be taken from what the client actually uploaded.
        $filename = Str::random(40).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("poses/{$pose->assistant_id}/{$pose->id}", $filename, 'public');

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

        return response()->json([
            'id' => $pose->id,
            'animation_url' => $animationFile->url,
            'animation_original_name' => $animationFile->original_name,
        ], 201);
    }

    private function destroyAnimation(Pose $pose): JsonResponse
    {
        $animationFile = $pose->animationFile;

        if (! $animationFile) {
            return response()->json(['message' => 'No pose animation file found.'], 404);
        }

        Storage::disk($animationFile->disk)->delete($animationFile->path);
        $animationFile->delete();

        return response()->json(['message' => 'Pose animation deleted']);
    }
}
