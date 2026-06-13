<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
	public function send(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'conversation_id' => ['nullable', 'exists:conversations,id'],
			'messages' => ['required', 'array'],
			'messages.*.role' => ['required', 'string'],
			'messages.*.content' => ['nullable', 'string'],
			'messages.*.images' => ['sometimes', 'array'],
		]);

		$user = $request->user();

		// Find existing or create new conversation
		$lastUserMessage = collect($validated['messages'])->last(fn ($m) => $m['role'] === 'user');

		$conversation = $validated['conversation_id']
			? $user->conversations()->findOrFail($validated['conversation_id'])
			: $user->conversations()->create([
				'title' => str($lastUserMessage['content'] ?? 'New chat')->limit(50)->toString(),
			]);

		// Save user message
		if ($lastUserMessage) {
			$conversation->messages()->create([
				'role' => 'user',
				'content' => $lastUserMessage['content'] ?? '',
				'image' => $lastUserMessage['images'][0] ?? null,
			]);
		}

		// Call Ollama
		$response = Http::timeout(120)->post(
			config('ai.ollama.url') . '/api/chat',
			[
				'model' => config('ai.ollama.model'),
				'stream' => false,
				'think' => true,
				'messages' => $validated['messages'],
			]
		);

		if ($response->failed()) {
			return response()->json(['message' => 'LLM service unavailable'], 502);
		}

		$data = $response->json();
		$content = $data['message']['content'] ?? '';
		$thinking = $data['message']['thinking'] ?? null;

		// Save assistant response
		$conversation->messages()->create([
			'role' => 'assistant',
			'content' => $content,
			'thinking' => $thinking,
		]);

		return response()->json([
			'conversation_id' => $conversation->id,
			'content' => $content,
			'thinking' => $thinking,
		]);
	}
}
