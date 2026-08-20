<?php

namespace App\Http\Controllers\Api;

use App\Directors\PromptDirector;
use App\DTOs\LlmResponse;
use App\Http\Controllers\Controller;
use App\Jobs\SummarizeConversation;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\DiscordChannel;
use App\Models\Image;
use App\Services\ImageGenProviders\ImageGenManager;
use App\Services\ImageGenProviders\ImageGenPromptEnhancer;
use App\Services\LlmProviders\LlmManager;
use App\Services\TtsProviders\TtsManager;
use App\Traits\ResolvesAssistantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
	use ResolvesAssistantUser;

	private const MESSAGES_PER_PAGE = 50;

	private const MEMORY_SUMMARY_TRIGGER_COUNT = 50;

	private const IMAGE_GEN_COMMAND = '/create-image ';

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

		$limit = self::MESSAGES_PER_PAGE;

		$query = $conversation->messages()
			->with('image')
			->orderByDesc('created_at');

		if ($request->has('before')) {
			$query->where('id', '<', (int) $request->input('before'));
		}

		// Fetch one extra to determine if older messages exist
		$messages = $query->take($limit + 1)->get();
		$hasMore = $messages->count() > $limit;

		// Trim to limit, reverse to chronological order
		$messages = $messages->take($limit)->reverse()->values();

		$messages->transform(function ($message) {
			$message->image_url = $message->image?->url;
			unset($message->image);
			return $message;
		});

		return response()->json([
			'messages' => $messages,
			'has_more' => $hasMore,
		]);
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
			'voice_mode' => ['sometimes', 'boolean'],
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

		$imageGenPrompt = $this->extractImageGenPrompt($lastUserMessage['content'] ?? null);

		if ($imageGenPrompt !== null) {
			if ($imageGenPrompt === '') {
				return response()->json(['message' => 'Describe what image to generate after /create-image.'], 422);
			}

			try {
				$generated = $this->generateImageMessage($request, $assistantUser, $conversation, $imageGenPrompt);
			} catch (\RuntimeException $e) {
				return response()->json(['message' => $e->getMessage()], 502);
			}

			return response()->json([
				'conversation_id' => $conversation->id,
				'content' => $generated['content'],
				'image_url' => $generated['image_url'],
				'thinking' => $generated['enhanced_prompt'],
				'emotion' => $generated['emotion'],
				'intimate' => $generated['intimate'],
				'tts_instructions' => null,
			]);
		}

		$assistantModel = $assistantUser->assistant;

		$emotions = [
			'regular' => $assistantModel->emotions()->where('restricted', false)->pluck('name')->toArray(),
			'intimate' => $assistantModel->emotions()->where('restricted', true)->pluck('name')->toArray(),
		];

		$archive = $request->user()->archives()->first();

		$excludedSections = ['opening_message'];

		if (! empty($validated['voice_mode'])) {
			$excludedSections[] = 'style rules';
			$excludedSections[] = 'OOC mode';

			if (empty($lastUserMessage['images'][0])) {
				$excludedSections[] = 'image handling';
			}
		} else {
			$excludedSections[] = 'voice mode';
		}

		$director = (new PromptDirector($assistantModel->prompt))
			->append('emotion tags', ['available emotions' => $emotions])
			->except($excludedSections);

		$voiceModel = null;

		if (! empty($validated['voice_mode'])) {
			$voiceModel = (new TtsManager())->resolveVoiceModel($assistantUser);

			if ($voiceModel?->provider->prompt) {
				$director->append('voice provider prompt', $voiceModel->provider->prompt);
			}

			if ($voiceModel?->prompt) {
				$director->append('voice model prompt', $voiceModel->prompt);
			}
		}

		if ($archive && ! empty($lastUserMessage['content'])) {
			$director->withRetrieval($lastUserMessage['content'], $archive->id);
		}

		$director->withLongTermMemory($conversation);

		$systemPrompt = $director->build();

		$tts = $voiceModel ? (new TtsManager())->fromModel($voiceModel) : null;

		try {
			$llm = (new LlmManager())->forAssistantUser($assistantUser);
			$response = $llm->chat(
				messages: [
					['role' => 'system', 'content' => $systemPrompt],
					...$validated['messages'],
				],
				options: $tts?->llmOptions() ?? [],
			);
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 502);
		}

		$content = $response->content;
		$ttsInstructions = null;
		if ($tts) {
			$result = $tts->parseLlmResponse($response->content);
			$content = $result->content;
			$ttsInstructions = $result->ttsInstructions;
		}

		$assistantMessage = $conversation->messages()->create([
			'role' => 'assistant',
			'content' => $content,
			'thinking' => $response->thinking,
		]);

		$this->checkpointAutoSummarize($conversation, $assistantMessage->id);

		return response()->json([
			'conversation_id' => $conversation->id,
			'content' => $content,
			'thinking' => $response->thinking,
			'tts_instructions' => $ttsInstructions,
		]);
	}

	private function extractImageGenPrompt(?string $content): ?string
	{
		$content = trim($content ?? '');

		if (! preg_match('/^\/create-image(?:\s+|$)/i', $content, $match)) {
			return null;
		}

		return trim(substr($content, strlen($match[0])));
	}

	/**
	 * Runs the full /create-image pipeline (enhance -> generate -> in-character reaction -> persist),
	 * shared by every channel (web, Discord, ...). Callers format their own channel-specific response.
	 *
	 * @return array{content: string, emotion: ?string, intimate: bool, image_url: string, enhanced_prompt: string}
	 *
	 * @throws \RuntimeException if enhancement, generation, or the reaction LLM call fails
	 */
	private function generateImageMessage(Request $request, AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt): array
	{
		$imageGenManager = new ImageGenManager();
		$imageGenModel = $imageGenManager->resolveImageGenModel($assistantUser);

		$enhancedPrompt = (new ImageGenPromptEnhancer())->enhance($rawPrompt, $assistantUser, $conversation, $imageGenModel);

		$provider = $imageGenModel ? $imageGenManager->fromModel($imageGenModel) : $imageGenManager->fromConfig();
		$result = $provider->generate($enhancedPrompt);

		$reaction = $this->reactToGeneratedImage($request, $assistantUser, $conversation, $rawPrompt, $enhancedPrompt);
		$parsed = $this->extractEmotionTag($reaction->content);

		$assistantMessage = $conversation->messages()->create([
			'role' => 'assistant',
			'content' => $parsed['content'],
			'emotion' => $parsed['emotion'],
		]);

		$storagePath = "messages/{$request->user()->id}/{$conversation->id}";
		$image = Image::storeFromBase64($result->imageData, $assistantMessage, $storagePath);

		$this->checkpointAutoSummarize($conversation, $assistantMessage->id);

		return [
			'content' => $parsed['content'],
			'emotion' => $parsed['emotion'],
			'intimate' => $parsed['intimate'],
			'image_url' => $image->url,
			'enhanced_prompt' => $enhancedPrompt,
		];
	}

	/**
	 * @return array{content: string, emotion: ?string, intimate: bool}
	 */
	private function extractEmotionTag(string $content): array
	{
		$emotion = null;
		$intimate = false;

		if (preg_match('/^\[([a-zA-Z]+)\]/', $content, $match)) {
			$emotion = $match[1];
			$content = trim(substr($content, strlen($match[0])));
		}

		if (preg_match('/^\[intimate\]/i', $content, $match)) {
			$intimate = true;
			$content = trim(substr($content, strlen($match[0])));
		}

		return ['content' => $content, 'emotion' => $emotion, 'intimate' => $intimate];
	}

	/**
	 * Gets Vera's natural in-character reaction to having just generated and sent an image,
	 * using the same persona/emotion-tag context as a normal chat reply.
	 */
	private function reactToGeneratedImage(Request $request, AssistantUser $assistantUser, Conversation $conversation, string $rawPrompt, string $enhancedPrompt): LlmResponse
	{
		$assistantModel = $assistantUser->assistant;

		$emotions = [
			'regular' => $assistantModel->emotions()->where('restricted', false)->pluck('name')->toArray(),
			'intimate' => $assistantModel->emotions()->where('restricted', true)->pluck('name')->toArray(),
		];

		$excludedSections = ['opening_message', 'voice mode'];

		if ($conversation->discord_channel_id) {
			// Discord has no UI to render an emotion tag against — same reasoning as the normal Discord reply flow.
			$excludedSections[] = 'emotion tags';
		}

		$director = (new PromptDirector($assistantModel->prompt))
			->append('emotion tags', ['available emotions' => $emotions])
			->except($excludedSections);

		$archive = $request->user()->archives()->first();
		if ($archive) {
			$director->withRetrieval($rawPrompt, $archive->id);
		}

		$director->withLongTermMemory($conversation);

		$history = $conversation->messages()
			->orderByDesc('created_at')
			->take(self::MESSAGES_PER_PAGE)
			->get(['role', 'content'])
			->reverse()
			->values()
			->map(fn ($m) => ['role' => $m->role, 'content' => $m->content ?? ''])
			->toArray();

		$history[] = [
			'role' => 'system',
			'content' => "[You just generated and are sending an image. What it depicts: \"{$enhancedPrompt}\"]",
		];

		$llm = (new LlmManager())->forAssistantUser($assistantUser);

		return $llm->chat(messages: [
			['role' => 'system', 'content' => $director->build()],
			...$history,
		]);
	}

	public function sendDiscordMessage(Request $request, int $assistant): JsonResponse
	{
		$validated = $request->validate([
			'channel_id' => ['required', 'string'],
			'message_id' => ['nullable', 'string'],
			'content' => ['nullable', 'string'],
			'images' => ['sometimes', 'array'],
			'dm_username' => ['nullable', 'string'],
		]);

		$assistantUser = $this->resolveAssistantUser($request, $assistant);

		if (! empty($validated['dm_username'])) {
			DiscordChannel::updateOrCreate(
				['discord_channel_id' => $validated['channel_id']],
				['discord_server_id' => null, 'name' => $validated['dm_username']],
			);
		}

		$conversation = $assistantUser->conversations()->firstOrCreate(
			['discord_channel_id' => $validated['channel_id']],
			['title' => 'New conversation'],
		);

		$message = $conversation->messages()->create([
			'role' => 'user',
			'discord_message_id' => $validated['message_id'] ?? null,
			'content' => $validated['content'] ?? '',
		]);

		if (! empty($validated['images'][0])) {
			$storagePath = "messages/{$request->user()->id}/{$conversation->id}";
			Image::storeFromBase64($validated['images'][0], $message, $storagePath);
		}

		$imageGenPrompt = $this->extractImageGenPrompt($validated['content'] ?? null);

		if ($imageGenPrompt !== null) {
			if ($imageGenPrompt === '') {
				return response()->json(['message' => 'Describe what image to generate after /create-image.'], 422);
			}

			try {
				$generated = $this->generateImageMessage($request, $assistantUser, $conversation, $imageGenPrompt);
			} catch (\RuntimeException $e) {
				return response()->json(['message' => $e->getMessage()], 502);
			}

			if ($conversation->title === 'New conversation') {
				$conversation->update([
					'title' => str($validated['content'] ?? '')->limit(50)->toString(),
				]);
			}

			return response()->json([
				'content' => $generated['content'],
				'image_url' => $generated['image_url'],
			]);
		}

		$assistantModel = $assistantUser->assistant;
		$archive = $request->user()->archives()->first();

		$director = (new PromptDirector($assistantModel->prompt))
			->except(['opening_message', 'voice mode', 'emotion tags']);

		if ($archive && ! empty($validated['content'])) {
			$director->withRetrieval($validated['content'], $archive->id);
		}

		$director->withLongTermMemory($conversation);
		$director->withDiscordEnvironment($conversation, $assistantUser);

		$systemPrompt = $director->build();

		$ownMessages = $conversation->messages()
			->orderBy('created_at')
			->get(['role', 'content', 'discord_message_id', 'created_at'])
			->map(fn ($m) => [
				'role' => $m->role,
				'content' => $m->content,
				'discord_message_id' => $m->discord_message_id,
				'created_at' => $m->created_at,
			]);

		$siblingMessages = Conversation::query()
			->whereHas('assistantUser', fn ($q) => $q->where('user_id', $request->user()->id))
			->where('discord_channel_id', $validated['channel_id'])
			->where('id', '!=', $conversation->id)
			->with('assistantUser.assistant')
			->get()
			->flatMap(function (Conversation $sibling) {
				$assistantName = $sibling->assistantUser->assistant->name;

				return $sibling->messages()
					->get(['role', 'content', 'discord_message_id', 'created_at'])
					->map(fn ($m) => [
						'role' => 'user',
						'content' => $m->role === 'assistant' ? "{$assistantName}: {$m->content}" : $m->content,
						'discord_message_id' => $m->discord_message_id,
						'created_at' => $m->created_at,
					]);
			});

		// A message mentioning multiple bots gets saved once per bot, into each bot's own
		// conversation — dedupe those by Discord message id so it isn't repeated in the merged view.
		$seenDiscordMessageIds = [];

		$history = $ownMessages
			->concat($siblingMessages)
			->sortBy('created_at')
			->values()
			->filter(function ($m) use (&$seenDiscordMessageIds) {
				if (! $m['discord_message_id']) {
					return true;
				}

				if (in_array($m['discord_message_id'], $seenDiscordMessageIds, true)) {
					return false;
				}

				$seenDiscordMessageIds[] = $m['discord_message_id'];

				return true;
			})
			->map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']])
			->values()
			->toArray();

		if (! empty($validated['images'][0]) && count($history) > 0) {
			$lastIndex = array_key_last($history);
			$history[$lastIndex]['images'] = [$validated['images'][0]];
		}

		try {
			$llm = (new LlmManager())->forAssistantUser($assistantUser);
			$response = $llm->chat(messages: [
				['role' => 'system', 'content' => $systemPrompt],
				...$history,
			]);
		} catch (\RuntimeException $e) {
			return response()->json(['message' => $e->getMessage()], 502);
		}

		$parsed = $this->extractEmotionTag($response->content);

		$assistantMessage = $conversation->messages()->create([
			'role' => 'assistant',
			'content' => $parsed['content'],
			'thinking' => $response->thinking,
			'emotion' => $parsed['emotion'] ?? 'neutral',
		]);

		if ($conversation->title === 'New conversation') {
			$conversation->update([
				'title' => str($validated['content'] ?? '')->limit(50)->toString(),
			]);
		}

		$this->checkpointAutoSummarize($conversation, $assistantMessage->id);

		return response()->json(['content' => $parsed['content']]);
	}

	private function checkpointAutoSummarize(Conversation $conversation, int $assistantMessageId): void
	{
		$checkpoint = $conversation->memory_checkpoint_message_id ?? 0;
		$pendingCount = $conversation->messages()->where('id', '>', $checkpoint)->count();

		if ($conversation->auto_summarize_enabled && $pendingCount >= self::MEMORY_SUMMARY_TRIGGER_COUNT) {
			$lockedAt = now()->toDateTimeString();

			$locked = $conversation->newQuery()
				->whereKey($conversation->id)
				->whereNull('memory_summarizing_at')
				->update(['memory_summarizing_at' => $lockedAt]);

			if ($locked === 1) {
				SummarizeConversation::dispatch($conversation, $assistantMessageId, $lockedAt);
			}
		}
	}
}