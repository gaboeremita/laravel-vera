<?php

namespace App\Http\Controllers\Api;

use App\Contracts\LlmProvider;
use App\Directors\PromptDirector;
use App\Http\Controllers\Controller;
use App\Models\Emotion;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
			->with('image')
			->orderBy('created_at')
			->get(['id', 'role', 'content', 'thinking', 'emotion', 'conversation_id']);

		$messages->transform(function ($message) {
			$message->image_url = $message->image?->url;
			unset($message->image);
			return $message;
		});

		return response()->json($messages);
	}

	public function store(Request $request): JsonResponse
	{
		$conversation = $request->user()
			->conversations()
			->create(['title' => 'New conversation']);

		return response()->json($conversation, 201);
	}

	public function destroy(Request $request, int $id): JsonResponse
	{
		$conversation = $request->user()
			->conversations()
			->findOrFail($id);

		$conversation->delete();

		return response()->json(['message' => 'Conversation deleted']);
	}

	public function sendMessage(Request $request, int $id): JsonResponse
	{
		$validated = $request->validate([
			'messages' => ['required', 'array'],
			'messages.*.role' => ['required', 'string', 'in:user,assistant'],
			'messages.*.content' => ['nullable', 'string'],
			'messages.*.images' => ['sometimes', 'array'],
		]);

		$conversation = $request->user()
			->conversations()
			->findOrFail($id);

		$lastUserMessage = collect($validated['messages'])->last(fn ($m) => $m['role'] === 'user');

		if ($lastUserMessage) {

			$message = $conversation->messages()->create([
				'role' => 'user',
				'content' => $lastUserMessage['content'] ?? '',
			]);

			if (! empty($lastUserMessage['images'][0])) {
				$storagePath = "messages/{$request->user()->id}/{$conversation->id}";
				Image::storeFromBase64($lastUserMessage['images'][0], $message, $storagePath);
			}
		}

		$emotions = [
			'regular' => Emotion::where('restricted', false)->pluck('name')->toArray(),
			'intimate' => Emotion::where('restricted', true)->pluck('name')->toArray(),
		];

		$systemPrompt = (new PromptDirector())
			->append('emotion tags', ['available emotions' => $emotions])
			->build();

		try {
			$llm = app(LlmProvider::class);
			$response = $llm->chat([
				['role' => 'system', 'content' => $systemPrompt],
				...$validated['messages'],
			]);
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 502);
		}

		$conversation->messages()->create([
			'role' => 'assistant',
			'content' => $response->content,
			'thinking' => $response->thinking,
		]);

		return response()->json([
			'conversation_id' => $conversation->id,
			'content' => $response->content,
			'thinking' => $response->thinking,
		]);
	}

	// Update conversation title
	public function update(Request $request, int $id): JsonResponse
	{
		$validated = $request->validate([
			'title' => ['required', 'string', 'max:100'],
		]);

		$conversation = $request->user()
			->conversations()
			->findOrFail($id);

		$conversation->update($validated);

		return response()->json($conversation);
	}
}