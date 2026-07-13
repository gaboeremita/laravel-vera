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
		return response()->json(
			Emotion::with(['image', 'video'])
				->where('assistant_id', $assistant)
				->get()
				->map(fn (Emotion $emotion) => [
					'name' => $emotion->name,
					'image_url' => $emotion->image?->url,
					'video_url' => $emotion->video?->url,
				])
		);
	}
}
