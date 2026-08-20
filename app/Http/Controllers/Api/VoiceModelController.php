<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceModelController extends Controller
{
	public function store(Request $request, int $providerId): JsonResponse
	{
		$provider = VoiceProvider::findOrFail($providerId);

		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'endpoint' => ['required', 'string', 'max:255'],
			'voices' => ['nullable', 'array'],
			'voices.*' => ['string'],
			'config' => ['nullable', 'array'],
		]);

		$model = $provider->models()->create($validated);

		return response()->json($model, 201);
	}

	public function update(Request $request, int $providerId, int $id): JsonResponse
	{
		$model = VoiceProvider::findOrFail($providerId)
			->models()
			->findOrFail($id);

		$validated = $request->validate([
			'name' => ['sometimes', 'string', 'max:255'],
			'endpoint' => ['sometimes', 'string', 'max:255'],
			'voices' => ['nullable', 'array'],
			'voices.*' => ['string'],
			'config' => ['nullable', 'array'],
		]);

		$model->update($validated);

		return response()->json($model);
	}

	public function destroy(int $providerId, int $id): JsonResponse
	{
		VoiceProvider::findOrFail($providerId)
			->models()
			->findOrFail($id)
			->delete();

		return response()->json(['message' => 'Model deleted']);
	}

	/**
	 * Voice models otherwise share the provider's seeded, read-only catalog nature — this only
	 * updates the prompt injected into voice-mode conversations, not the model's config.
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
