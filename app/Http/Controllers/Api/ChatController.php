<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
	public function send(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'messages' => ['required', 'array'],
			'messages.*.role' => ['required', 'string'],
			'messages.*.content' => ['required', 'string'],
			'messages.*.images' => ['sometimes', 'array'],
		]);

		$response = Http::post(
			config('ai.ollama.url') . '/api/chat',
			[
				'model' => config('ai.ollama.model'),
				'stream' => false,
				'think' => true,
				'messages' => $validated['messages'],
			]
		);

		if ($response->failed()) {
			return response()->json([
				'message' => 'LLM service unavailable',
			], 502);
		}

		$data = $response->json();

		return response()->json([
			'content' => $data['message']['content'] ?? '',
			'thinking' => $data['message']['thinking'] ?? null,
		]);
	}
}
