<?php

namespace App\Http\Controllers\Api;

use App\Directors\PromptDirector;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\LlmProviders\LlmManager;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
	use ResolvesAssistantUser;

	public function index(Request $request, int $assistant): JsonResponse
	{

		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$conversations = $assistantUser
			->conversations()
			->orderByDesc('updated_at')
			->get(['id', 'title', 'updated_at']);

		return response()->json($conversations);
	}

	public function show(Request $request, int $assistant, int $id): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$conversation = $assistantUser
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

	public function store(Request $request, int $assistant): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$conversation = $assistantUser
			->conversations()
			->create(['title' => 'New conversation']);

		$conversation->messages()->create([
			'role' => 'assistant',
			'content' => $assistantUser->assistant->opening_message ?? '',
		]);

		return response()->json($conversation, 201);
	}

	public function destroy(Request $request, int $assistant, int $id): JsonResponse
	{
		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$conversation = $assistantUser
			->conversations()
			->findOrFail($id);

		$conversation->delete();

		return response()->json(['message' => 'Conversation deleted']);
	}

	public function update(Request $request, int $assistant, int $id): JsonResponse
	{
		$validated = $request->validate([
			'title' => ['required', 'string', 'max:100'],
		]);

		$conversation = $this->resolveAssistantUser($request, $assistant)
			->conversations()
			->findOrFail($id);

		$conversation->update($validated);

		return response()->json($conversation);
	}

	public function sendMessage(Request $request, int $assistant, int $id): JsonResponse
	{
		$validated = $request->validate([
			'messages' => ['required', 'array'],
			'messages.*.role' => ['required', 'string', 'in:user,assistant'],
			'messages.*.content' => ['nullable', 'string'],
			'messages.*.images' => ['sometimes', 'array'],
		]);

		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		$conversation = $assistantUser
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

		$assistantModel = $assistantUser->assistant;

		$emotions = [
			'regular' => $assistantModel->emotions()->where('restricted', false)->pluck('name')->toArray(),
			'intimate' => $assistantModel->emotions()->where('restricted', true)->pluck('name')->toArray(),
		];

		$archive = $request->user()->archives()->first();

		$director = (new PromptDirector($assistantModel->prompt))
			->append('emotion tags', ['available emotions' => $emotions])
			->except(['opening_message']);

		if ($archive && ! empty($lastUserMessage['content'])) {
			$director->withRetrieval($lastUserMessage['content'], $archive->id);
		}

		$systemPrompt = $director->build();

		try {
			$llm = (new LlmManager())->forAssistantUser($assistantUser);
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
}