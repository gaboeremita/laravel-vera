<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\ValidPromptStructure;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantMemoryPromptController extends Controller
{
	use ResolvesAssistantUser;

	public function show(Request $request, int $assistant): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		return response()->json($assistantUser->memory_prompt ?? (object) []);
	}

	public function update(Request $request, int $assistant): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$validated = $request->validate([
			'memory_prompt' => [
				'present',
				'nullable',
				'array',
				function (string $attribute, mixed $value, \Closure $fail): void {
					if ($value === null || $value === []) {
						return;
					}

					(new ValidPromptStructure())->validate($attribute, $value, $fail);
				},
			],
		]);

		$assistantUser->update([
			'memory_prompt' => ($validated['memory_prompt'] ?? null) ?: null,
		]);

		return response()->json($assistantUser->memory_prompt ?? (object) []);
	}
}
