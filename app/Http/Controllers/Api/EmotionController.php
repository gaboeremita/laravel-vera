<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Emotion;
use App\Models\Pose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmotionController extends Controller
{
    public function index(Request $request, int $assistant): JsonResponse
    {
        $assistantModel = $request->user()
            ->assistants()
            ->with('vrm')
            ->findOrFail($assistant);

        $emotions = Emotion::with(['image', 'video'])
            ->where('assistant_id', $assistant)
            ->get()
            ->map(fn (Emotion $emotion) => [
                'name' => $emotion->name,
                'image_url' => $emotion->image?->url,
                'video_url' => $emotion->video?->url,
                'vrm_blendshapes' => $emotion->vrm_blendshapes,
            ]);

        $poses = Pose::with('animationFile')
            ->where('assistant_id', $assistant)
            ->get()
            ->map(fn (Pose $pose) => [
                'id' => $pose->id,
                'name' => $pose->name,
                'vrm_blendshapes' => $pose->vrm_blendshapes,
                'animation_url' => $pose->animationFile?->url,
                'animation_original_name' => $pose->animationFile?->original_name,
            ]);

        return response()->json([
            'portrait_type' => $assistantModel->portrait_type->value,
            'vrm_url' => $assistantModel->vrm?->url,
            'emotions' => $emotions,
            'poses' => $poses,
        ]);
    }
}
