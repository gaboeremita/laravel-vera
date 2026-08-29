<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Emotion;
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

        return response()->json([
            'portrait_type' => $assistantModel->portrait_type->value,
            'vrm_url' => $assistantModel->vrm?->url,
            'emotions' => $emotions,
        ]);
    }
}
