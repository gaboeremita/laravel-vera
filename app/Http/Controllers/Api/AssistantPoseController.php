<?php

namespace App\Http\Controllers\Api;

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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'vrm_blendshapes' => ['sometimes', 'array'],
            'vrm_blendshapes.*.expression' => ['required', 'string', 'max:100'],
            'vrm_blendshapes.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

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
        ], 201);
    }

    public function update(Request $request, int $assistantId, int $poseId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        $pose = $assistant->poses()->findOrFail($poseId);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'vrm_blendshapes' => ['sometimes', 'array'],
            'vrm_blendshapes.*.expression' => ['required', 'string', 'max:100'],
            'vrm_blendshapes.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $pose->name) {
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
        ]);
    }

    public function destroy(Request $request, int $assistantId, int $poseId): JsonResponse
    {
        $assistant = $request->user()
            ->assistants()
            ->findOrFail($assistantId);

        $pose = $assistant->poses()->findOrFail($poseId);

        if ($pose->animationFile) {
            Storage::disk($pose->animationFile->disk)->delete($pose->animationFile->path);
        }

        $pose->delete();

        return response()->json(['message' => 'Pose deleted']);
    }
}
