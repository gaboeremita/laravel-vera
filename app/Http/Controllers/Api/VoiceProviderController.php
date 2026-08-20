<?php

namespace App\Http\Controllers\Api;

use App\Enums\VoiceProviderFormat;
use App\Http\Controllers\Controller;
use App\Models\VoiceProvider;
use App\Rules\ValidPromptStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class VoiceProviderController extends Controller
{
	public function index(): JsonResponse
	{
		return response()->json(
			VoiceProvider::with('models')->get()
		);
	}

	public function store(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'url' => ['required', 'string', 'url', 'max:255'],
			'api_key' => ['nullable', 'string'],
			'format' => ['required', new Enum(VoiceProviderFormat::class)],
			'instructions' => ['nullable', 'string'],
		]);

		$provider = VoiceProvider::create($validated);

		return response()->json($provider, 201);
	}

	public function update(Request $request, int $id): JsonResponse
	{
		$provider = VoiceProvider::findOrFail($id);

		$validated = $request->validate([
			'name' => ['sometimes', 'string', 'max:255'],
			'url' => ['sometimes', 'string', 'url', 'max:255'],
			'api_key' => ['sometimes', 'string'],
			'format' => ['sometimes', new Enum(VoiceProviderFormat::class)],
			'instructions' => ['nullable', 'string'],
		]);

		$provider->update($validated);

		return response()->json($provider);
	}

	public function destroy(int $id): JsonResponse
	{
		VoiceProvider::findOrFail($id)->delete();

		return response()->json(['message' => 'Provider deleted']);
	}

	/**
	 * Prompt editing stays separate from the general config update above — it's a
	 * structured JSON tree (see ValidPromptStructure) driven by its own UI (PromptTreeEditor),
	 * not a plain-string field alongside url/api_key/format.
	 */
	public function updatePrompt(Request $request, int $id): JsonResponse
	{
		$validated = $request->validate([
			'prompt' => ['nullable', 'array', new ValidPromptStructure],
		]);

		$provider = VoiceProvider::findOrFail($id);
		$provider->update($validated);

		return response()->json($provider);
	}
}
