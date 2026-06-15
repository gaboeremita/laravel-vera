<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Emotion;
use Illuminate\Http\JsonResponse;

class EmotionController extends Controller
{
	public function index(): JsonResponse
	{
		$query = Emotion::with(['image', 'video']);

		// Swap the emotion set based on unlocked state
		$query->where('restricted', request()->boolean('unlocked'));

		return response()->json(
			$query->get()->map(fn (Emotion $emotion) => [
				'name' => $emotion->name,
				'image_url' => $emotion->image?->url,
				'video_url' => $emotion->video?->url,
			])
		);
	}
}
