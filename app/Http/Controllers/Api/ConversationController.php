<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConversationController extends Controller
{
	public function index(Request $request): JsonResponse
	{
		$conversations = $request->user()
			->conversations()
			->orderByDesc('updated_at')
			->get(['id', 'title', 'updated_at']);

		return response()->json($conversations);
	}

	public function show(Request $request, int $id): JsonResponse
	{
		$conversation = $request->user()
			->conversations()
			->findOrFail($id);

		$messages = $conversation->messages()
			->orderBy('created_at')
			->get(['id', 'role', 'content', 'thinking', 'image', 'emotion']);

		return response()->json($messages);
	}

	public function store(Request $request): JsonResponse
	{
		$conversation = $request->user()
			->conversations()
			->create(['title' => 'New conversation']);

		return response()->json($conversation, 201);
	}

	public function sendMessage(Request $request, string $id): JsonResponse
	{
		$validated = $request->validate([
			'messages' => ['required', 'array'],
			'messages.*.role' => ['required', 'string'],
			'messages.*.content' => ['nullable', 'string'],
			'messages.*.images' => ['sometimes', 'array'],
		]);

		// Get conversation scoped to authenticated user
		$conversation = $request->user()
			->conversations()
			->findOrFail($id);

		// Save user message
		$lastUserMessage = collect($validated['messages'])->last(fn ($m) => $m['role'] === 'user');

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
