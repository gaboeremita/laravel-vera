<?php

namespace App\Http\Controllers\Api;

use App\Actions\StorePoseAnimation;
use App\Enums\AssistantPortraitType;
use App\Enums\Posture;
use App\Http\Controllers\Controller;
use App\Models\Pose;
use App\Models\PoseAnimationFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssistantPoseAnimationController extends Controller
{
    public function store(Request $request, StorePoseAnimation $storePoseAnimation, int $assistantId, int $poseId): JsonResponse
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

        return $this->storeAnimation($request, $pose, $storePoseAnimation);
    }

    /**
     * Uploads (creating the default pose first if it doesn't exist yet) the
     * assistant's default pose animation — see AssistantPoseController::updateDefault.
     */
    public function storeDefault(Request $request, StorePoseAnimation $storePoseAnimation, int $assistantId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        if ($assistant->portrait_type !== AssistantPortraitType::Avatar3D) {
            return response()->json([
                'message' => 'Poses are only available for 3D avatar assistants.',
            ], 422);
        }

        $pose = $assistant->poses()->firstOrCreate(['name' => 'default', 'posture' => $this->requestedPosture($request)]);

        return $this->storeAnimation($request, $pose, $storePoseAnimation);
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

        $pose = $assistant->poses()->where('name', 'default')->where('posture', $this->requestedPosture($request))->first();

        if (! $pose) {
            return response()->json(['message' => 'No pose animation file found.'], 404);
        }

        return $this->destroyAnimation($pose);
    }

    private function requestedPosture(Request $request): string
    {
        return $request->validate(['posture' => ['sometimes', Rule::enum(Posture::class)]])['posture'] ?? Posture::Standing->value;
    }

    private function storeAnimation(Request $request, Pose $pose, StorePoseAnimation $storePoseAnimation): JsonResponse
    {
        $request->validate([
            'animation' => ['required', 'file', 'extensions:vrma,fbx', 'max:10240'],
        ]);

        $animationFile = $storePoseAnimation->handle($pose, $request->file('animation'));

        return response()->json([
            'id' => $pose->id,
            'posture' => $pose->posture->value,
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

        $animationFile->delete();
        PoseAnimationFile::releaseStorage($animationFile->disk, $animationFile->path);

        return response()->json(['message' => 'Pose animation deleted']);
    }
}
