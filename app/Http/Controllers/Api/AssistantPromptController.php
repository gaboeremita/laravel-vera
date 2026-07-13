<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\ValidPromptStructure;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class AssistantPromptController extends Controller
{
	use ResolvesAssistantUser;

	public function show(Request $request, int $assistant): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		return response()->json($assistantUser->assistant->prompt);
	}

	public function store(Request $request, int $assistant): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);
		$model = $assistantUser->assistant;

		if (! empty($model->prompt)) {
			return response()->json(['message' => 'Prompt already exists.'], 409);
		}

		$validated = $request->validate([
			'prompt' => ['required', 'array', new ValidPromptStructure],
		]);

		$model->update(['prompt' => $validated['prompt']]);

		return response()->json($model->prompt, 201);
	}

	public function update(Request $request, int $assistant): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$validated = $request->validate([
			'prompt' => ['required', 'array', new ValidPromptStructure],
		]);

		$assistantUser->assistant->update(['prompt' => $validated['prompt']]);

		return response()->json($assistantUser->assistant->prompt);
	}

	public function destroy(Request $request, int $assistant): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$assistantUser->assistant->update(['prompt' => []]);

		return response()->json(['message' => 'Prompt deleted.']);
	}
}
