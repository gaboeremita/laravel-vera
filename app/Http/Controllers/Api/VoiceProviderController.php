<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceProviderController extends Controller
{
	public function index(): JsonResponse
	{
		return response()->json(
			VoiceProvider::with('models')->get()
		);
	}

	/**
	 * Voice providers are otherwise a seeded, read-only catalog — this only updates
	 * the prompt injected into voice-mode conversations, not the provider's config.
	 */
	public function updatePrompt(Request $request, int $id): JsonResponse
	{
		$validated = $request->validate([
			'prompt' => ['nullable', 'array'],
		]);

		$provider = VoiceProvider::findOrFail($id);
		$provider->update($validated);

		return response()->json($provider);
	}
}
