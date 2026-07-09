<?php

namespace App\Console\Commands;

use App\Contracts\LlmProvider;
use App\Directors\PromptDirector;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
	protected $signature = 'services:telegram';

	protected $description = 'Start VERA\'s Telegram bot using long polling';

	private TelegramService $telegram;

	private ?int $activeConversationId = null;

	private User $user;

	public function handle(): int
	{
		$config = config('ai.telegram');

		$this->telegram = new TelegramService(
			url: $config['url'],
			token: $config['token'],
		);

		$this->user = User::findOrFail($config['user_id']);
		$this->info('VERA Telegram bot started. Listening...');

		$offset = 0;
		$timeout = $config['poll_timeout'];

		while (true) {
			try {
				$updates = $this->telegram->getUpdates($offset, $timeout);

				foreach ($updates as $update) {
					$offset = $update['update_id'] + 1;
					$this->processUpdate($update);
				}
			} catch (\Exception $e) {
				$this->error("Poll error: {$e->getMessage()}");
				// Wait before retrying on connection failure
				sleep(5);
			}
		}
	}

	private function processUpdate(array $update): void
	{
		$message = $update['message'] ?? null;
		if (! $message) {
			return;
		}

		$chatId = $message['chat']['id'];

		$allowedChatId = config('ai.telegram.chat_id');
		if ($allowedChatId && $chatId !== $allowedChatId) {
			return;
		}

		$text = trim($message['text'] ?? $message['caption'] ?? '');
		$image = null;

		if (! empty($message['photo'])) {

			$photo = end($message['photo']);
			$filePath = $this->telegram->getFilePath($photo['file_id']);
			$image = $this->telegram->downloadFileAsBase64($filePath);
		}

		if (! $text && ! $image) {
			return;
		}

		if ($text && str_starts_with($text, '/')) {
			$this->handleCommand($chatId, $text);
		} else {
			$this->handleChat($chatId, $text, $image);
		}
	}

	private function handleCommand(int $chatId, string $text): void
	{
		$parts = explode(' ', $text, 2);
		$command = strtolower($parts[0]);
		$argument = $parts[1] ?? null;

		match ($command) {
			'/list' => $this->commandList($chatId),
			'start', '/new' => $this->commandNew($chatId),
			'/current' => $this->commandCurrent($chatId),
			'/switch' => $this->commandSwitch($chatId, $argument),
			default => $this->telegram->sendMessage($chatId, "Unknown command: {$command}"),
		};
	}

	private function commandList(int $chatId): void
	{
		$conversations = $this->user->conversations()
			->orderByDesc('updated_at')
			->get(['id', 'title', 'updated_at']);

		if ($conversations->isEmpty()) {
			$this->telegram->sendMessage($chatId, 'No conversations found. Use /new to start one.');

			return;
		}

		$lines = $conversations->map(function ($conv, $i) {
			$marker = $conv->id === $this->activeConversationId ? ' ◀' : '';

			return ($i + 1) . ". {$conv->title}{$marker}";
		});

		$this->telegram->sendMessage($chatId, "Conversations:\n\n" . $lines->implode("\n"));
	}

	private function commandNew(int $chatId): void
	{
		$conversation = $this->user->conversations()->create([
			'title' => 'New conversation',
		]);

		$this->activeConversationId = $conversation->id;
		$this->telegram->sendMessage($chatId, 'New conversation started.');
	}

	private function commandCurrent(int $chatId): void
	{
		if (! $this->activeConversationId) {
			$this->telegram->sendMessage($chatId, 'No active conversation. Use /list or /new.');

			return;
		}

		$conversation = $this->user->conversations()->find($this->activeConversationId);

		if (! $conversation) {
			$this->telegram->sendMessage($chatId, 'Active conversation not found.');
			$this->activeConversationId = null;

			return;
		}

		$this->telegram->sendMessage($chatId, "Active: {$conversation->title}");
	}

	private function commandSwitch(int $chatId, ?string $name): void
	{
		if (! $name) {
			$this->telegram->sendMessage($chatId, 'Usage: /switch conversation name');

			return;
		}

		$matches = $this->user->conversations()
			->where('title', 'like', "%{$name}%")
			->orderByDesc('updated_at')
			->get(['id', 'title']);

		if ($matches->isEmpty()) {
			$this->telegram->sendMessage($chatId, "No conversation matching \"{$name}\".");

			return;
		}

		if ($matches->count() > 1) {
			$list = $matches->map(fn ($c, $i) => ($i + 1) . ". {$c->title}")->implode("\n");
			$this->telegram->sendMessage($chatId, "Multiple matches:\n\n{$list}\n\nBe more specific.");

			return;
		}

		$this->activeConversationId = $matches->first()->id;
		$this->telegram->sendMessage($chatId, "Switched to: {$matches->first()->title}");
	}


	private function handleChat(int $chatId, string $text, ?string $image = null): void
	{
		$this->resolveConversation();

		if (! $this->activeConversationId) {
			$this->commandNew($chatId);
		}

		$conversation = $this->user->conversations()->find($this->activeConversationId);

		if (! $conversation) {
			$this->commandNew($chatId);
			$conversation = $this->user->conversations()->find($this->activeConversationId);
		}

		$this->telegram->sendTypingAction($chatId);

		// Save user message
		$conversation->messages()->create([
			'role' => 'user',
			'content' => $text,
		]);

		// Load full conversation history from DB
		$history = $conversation->messages()
			->orderBy('created_at')
			->get(['role', 'content'])
			->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
			->toArray();

		if ($image && count($history) > 0) {
			$lastIndex = array_key_last($history);
			$history[$lastIndex]['images'] = [$image];
		}

		$director = new PromptDirector();
		$lorebook = $this->user->lorebooks()->first();

		if ($lorebook && !empty($text)) {
			$director->withRetrieval($text, $lorebook->id);
		}

		$systemPrompt = $director
			->except(["emotion tags", "opening_message"])
			->build();

		if ($lorebook && !empty($lastUserMessage['content'])) {
			$director->withRetrieval($lastUserMessage['content'], $lorebook->id);
		}
		
		try {
			$llm = app(LlmProvider::class);
			$response = $llm->chat([
				['role' => 'system', 'content' => $systemPrompt],
				...$history,
			]);
		} catch (\RuntimeException $e) {
			$this->telegram->sendMessage($chatId, 'Connection to The Bridge failed. Try again.');
			$this->error("LLM error: {$e->getMessage()}");

			return;
		}

		// Strip emotion tag and persist it separately
		$content = $response->content;
		$emotion = 'neutral';

		if (preg_match('/^\[([a-z]+)\]/', $content, $match)) {
			$emotion = $match[1];
			$content = trim(substr($content, strlen($match[0])));
		}

		$conversation->messages()->create([
			'role' => 'assistant',
			'content' => $content,
			'thinking' => $response->thinking,
			'emotion' => $emotion,
		]);

		// Auto-title from first user message
		if ($conversation->title === 'New conversation') {
			$conversation->update([
				'title' => str($text)->limit(50)->toString(),
			]);
		}

		$this->telegram->sendMessage($chatId, $content);
		$this->info("Processed. Emotion: {$emotion}");
	}

	/**
	 * Falls back to the most recently updated conversation if none is active.
	 */
	private function resolveConversation(): void
	{
		if ($this->activeConversationId) {
			return;
		}

		$latest = $this->user->conversations()
			->orderByDesc('updated_at')
			->first();

		if ($latest) {
			$this->activeConversationId = $latest->id;
		}
	}
}