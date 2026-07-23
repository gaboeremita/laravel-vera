<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceModelController extends Controller
{
	/**
	 * Voice models are otherwise a seeded, read-only catalog — this only updates
	 * the prompt injected into voice-mode conversations, not the model's config.
	 */
	public function updatePrompt(Request $request, int $providerId, int $id): JsonResponse
	{
		$validated = $request->validate([
			'prompt' => ['nullable', 'array'],
		]);

		$model = VoiceProvider::findOrFail($providerId)
			->models()
			->findOrFail($id);

		$model->update($validated);

		return response()->json($model);
	}
}
