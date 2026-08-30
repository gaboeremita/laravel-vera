<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssistantPortraitType;
use App\Http\Controllers\Controller;
use App\Models\Pose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        if ($assistant->poses()->where('name', $validated['name'])->exists()) {
            return response()->json([
                'message' => 'This pose name already exists.',
                'errors' => ['name' => ['This pose name already exists.']],
            ], 422);
        }

        $pose = $assistant->poses()->create([
            'name' => $validated['name'],
            'vrm_blendshapes' => Pose::normalizeBlendshapes($validated['vrm_blendshapes'] ?? null),
        ]);

        return response()->json([
            'id' => $pose->id,
            'name' => $pose->name,
            'vrm_blendshapes' => $pose->vrm_blendshapes,
            'animation_url' => null,
            'animation_original_name' => null,
        ], 201);
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
            'vrm_blendshapes' => ['sometimes', 'array'],
            'vrm_blendshapes.*.expression' => ['required', 'string', 'max:100'],
            'vrm_blendshapes.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $pose->name) {
            if (strtolower($validated['name']) === 'default') {
                return response()->json([
                    'message' => 'This name is reserved for the default pose.',
                    'errors' => ['name' => ['This name is reserved for the default pose.']],
                ], 422);
            }

            if ($assistant->poses()->where('name', $validated['name'])->exists()) {
                return response()->json([
                    'message' => 'This pose name already exists.',
                    'errors' => ['name' => ['This pose name already exists.']],
                ], 422);
            }

            $pose->update(['name' => $validated['name']]);
        }

        if (array_key_exists('vrm_blendshapes', $validated)) {
            $pose->update(['vrm_blendshapes' => Pose::normalizeBlendshapes($validated['vrm_blendshapes'])]);
        }

        $pose->load('animationFile');

        return response()->json([
            'id' => $pose->id,
            'name' => $pose->name,
            'vrm_blendshapes' => $pose->vrm_blendshapes,
            'animation_url' => $pose->animationFile?->url,
            'animation_original_name' => $pose->animationFile?->original_name,
        ]);
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
     * default pose — a name-locked, undeletable pose always named "default",
     * mirroring how the image-mode "default" emotion works. Left unconfigured
     * (no blendshapes, no animation), the avatar simply falls back to its
     * existing hardcoded idle/neutral state.
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
            'vrm_blendshapes' => ['sometimes', 'array'],
            'vrm_blendshapes.*.expression' => ['required', 'string', 'max:100'],
            'vrm_blendshapes.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $pose = $assistant->poses()->updateOrCreate(
            ['name' => 'default'],
            ['vrm_blendshapes' => Pose::normalizeBlendshapes($validated['vrm_blendshapes'] ?? null)]
        );

        $pose->load('animationFile');

        return response()->json([
            'id' => $pose->id,
            'name' => $pose->name,
            'vrm_blendshapes' => $pose->vrm_blendshapes,
            'animation_url' => $pose->animationFile?->url,
            'animation_original_name' => $pose->animationFile?->original_name,
        ]);
    }
}
