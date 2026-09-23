<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssistantPortraitType;
use App\Enums\Posture;
use App\Http\Controllers\Controller;
use App\Models\Pose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AssistantPoseController extends Controller
{
    public function store(Request $request, int $assistantId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        if ($assistant->portrait_type !== AssistantPortraitType::Avatar3D) {
            return response()->json([
                'message' => 'Poses are only available for 3D avatar assistants.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'posture' => ['sometimes', Rule::enum(Posture::class)],
            'vrm_blendshapes' => ['sometimes', 'array'],
            'vrm_blendshapes.*.expression' => ['required', 'string', 'max:100'],
            'vrm_blendshapes.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (strtolower($validated['name']) === 'default') {
            return response()->json([
                'message' => 'The default pose is managed separately and cannot be created here.',
                'errors' => ['name' => ['The default pose is managed separately and cannot be created here.']],
            ], 422);
        }

        $posture = $validated['posture'] ?? Posture::Standing->value;

        if ($assistant->poses()->where('name', $validated['name'])->where('posture', $posture)->exists()) {
            return $this->duplicateNameResponse($posture);
        }

        $pose = $assistant->poses()->create([
            'name' => $validated['name'],
            'posture' => $posture,
            'vrm_blendshapes' => Pose::normalizeBlendshapes($validated['vrm_blendshapes'] ?? null),
        ]);

        return response()->json($this->poseJson($pose), 201);
    }

    public function update(Request $request, int $assistantId, int $poseId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        $pose = $assistant->poses()->findOrFail($poseId);

        if ($pose->name === 'default') {
            return response()->json([
                'message' => 'The default pose is managed separately.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'posture' => ['sometimes', Rule::enum(Posture::class)],
            'vrm_blendshapes' => ['sometimes', 'array'],
            'vrm_blendshapes.*.expression' => ['required', 'string', 'max:100'],
            'vrm_blendshapes.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $name = $validated['name'] ?? $pose->name;
        $posture = $validated['posture'] ?? $pose->posture->value;

        if ($name !== $pose->name || $posture !== $pose->posture->value) {
            if (strtolower($name) === 'default') {
                return response()->json([
                    'message' => 'This name is reserved for the default pose.',
                    'errors' => ['name' => ['This name is reserved for the default pose.']],
                ], 422);
            }

            if ($assistant->poses()->where('name', $name)->where('posture', $posture)->exists()) {
                return $this->duplicateNameResponse($posture);
            }

            $pose->update(['name' => $name, 'posture' => $posture]);
        }

        if (array_key_exists('vrm_blendshapes', $validated)) {
            $pose->update(['vrm_blendshapes' => Pose::normalizeBlendshapes($validated['vrm_blendshapes'])]);
        }

        return response()->json($this->poseJson($pose));
    }

    public function destroy(Request $request, int $assistantId, int $poseId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        $pose = $assistant->poses()->findOrFail($poseId);

        if ($pose->name === 'default') {
            return response()->json([
                'message' => 'The default pose cannot be deleted.',
            ], 422);
        }

        if ($pose->animationFile) {
            Storage::disk($pose->animationFile->disk)->delete($pose->animationFile->path);
        }

        $pose->delete();

        return response()->json(['message' => 'Pose deleted']);
    }

    /**
     * Updates (creating first if it doesn't exist yet) the assistant's
     * default pose for a posture — a name-locked, undeletable pose always
     * named "default", mirroring how the image-mode "default" emotion works.
     * Left unconfigured (no blendshapes, no animation), the avatar simply
     * falls back to its existing hardcoded idle/neutral state.
     */
    public function updateDefault(Request $request, int $assistantId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        if ($assistant->portrait_type !== AssistantPortraitType::Avatar3D) {
            return response()->json([
                'message' => 'Poses are only available for 3D avatar assistants.',
            ], 422);
        }

        $validated = $request->validate([
            'posture' => ['sometimes', Rule::enum(Posture::class)],
            'vrm_blendshapes' => ['sometimes', 'array'],
            'vrm_blendshapes.*.expression' => ['required', 'string', 'max:100'],
            'vrm_blendshapes.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $pose = $assistant->poses()->updateOrCreate(
            ['name' => 'default', 'posture' => $validated['posture'] ?? Posture::Standing->value],
            ['vrm_blendshapes' => Pose::normalizeBlendshapes($validated['vrm_blendshapes'] ?? null)]
        );

        return response()->json($this->poseJson($pose));
    }

    /**
     * @return array{id: int, name: string, posture: string, vrm_blendshapes: ?array, animation_url: ?string, animation_original_name: ?string}
     */
    private function poseJson(Pose $pose): array
    {
        $pose->load('animationFile');

        return [
            'id' => $pose->id,
            'name' => $pose->name,
            'posture' => $pose->posture->value,
            'vrm_blendshapes' => $pose->vrm_blendshapes,
            'animation_url' => $pose->animationFile?->url,
            'animation_original_name' => $pose->animationFile?->original_name,
        ];
    }

    private function duplicateNameResponse(string $posture): JsonResponse
    {
        $message = "A {$posture} pose with this name already exists.";

        return response()->json(['message' => $message, 'errors' => ['name' => [$message]]], 422);
    }
}
