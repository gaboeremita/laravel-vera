<?php

namespace App\Console\Commands;

use App\Directors\PromptDirector;
use App\Exceptions\TelegramApiException;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\User;
use App\Services\LlmProviders\LlmManager;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelegramPollCommand extends Command
{
	protected $signature = 'services:telegram';

	protected $description = 'Start VERA\'s Telegram bot using long polling';

	private TelegramService $telegram;

	private ?int $activeConversationId = null;

	private User $user;
	private AssistantUser $assistantUser;
	private Assistant $assistant;

	public function handle(): int
	{
		$config = config('ai.telegram');

		$this->telegram = new TelegramService(
			url: $config['url'],
			token: $config['token'],
		);

		$this->user = User::findOrFail($config['user_id']);

		$this->assistantUser = AssistantUser::where('user_id', $this->user->id)
			->where('assistant_id', $config['assistant_id'])
			->firstOrFail();
		$this->assistant = $this->assistantUser->assistant;
		$this->info('VERA Telegram bot started. Listening...');
		Log::info('VERA Telegram bot started. Listening...');

		$offset = 0;
		$timeout = $config['poll_timeout'];

		while (true) {
			try {
				$updates = $this->telegram->getUpdates($offset, $timeout);

				foreach ($updates as $update) {
					$offset = $update['update_id'] + 1;

					try {
						$this->processUpdate($update);
					} catch (\Throwable $e) {
						$this->error("Update processing error: {$e->getMessage()}");
						Log::error('Telegram update processing failed', [
							'update_id' => $update['update_id'] ?? null,
							'exception' => $e->getMessage(),
						]);
					}
				}
			} catch (\Throwable $e) {
				$retryAfter = $e instanceof TelegramApiException ? ($e->retryAfter ?? 5) : 5;

				$this->error("Poll error: {$e->getMessage()}");
				Log::error('Telegram poll error', [
					'exception' => $e->getMessage(),
					'retry_after' => $retryAfter,
				]);

				// Wait before retrying on connection failure, respecting Telegram's flood control when given
				sleep($retryAfter);
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
			default => $this->trySendMessage($chatId, "Unknown command: {$command}"),
		};
	}

	private function commandList(int $chatId): void
	{
		$conversations = $this->assistantUser->conversations()
			->orderByDesc('updated_at')
			->get(['id', 'title', 'updated_at']);

		if ($conversations->isEmpty()) {
			$this->trySendMessage($chatId, 'No conversations found. Use /new to start one.');

			return;
		}

		$lines = $conversations->map(function ($conv, $i) {
			$marker = $conv->id === $this->activeConversationId ? ' ◀' : '';

			return ($i + 1) . ". {$conv->title}{$marker}";
		});

		$this->trySendMessage($chatId, "Conversations:\n\n" . $lines->implode("\n"));
	}

	private function commandNew(int $chatId): void
	{
		$conversation = $this->assistantUser->conversations()->create([
			'title' => 'New conversation',
		]);

		$this->activeConversationId = $conversation->id;
		$this->trySendMessage($chatId, 'New conversation started.');
	}

	private function commandCurrent(int $chatId): void
	{
		if (! $this->activeConversationId) {
			$this->trySendMessage($chatId, 'No active conversation. Use /list or /new.');

			return;
		}

		$conversation = $this->assistantUser->conversations()->find($this->activeConversationId);

		if (! $conversation) {
			$this->trySendMessage($chatId, 'Active conversation not found.');
			$this->activeConversationId = null;

			return;
		}

		$this->trySendMessage($chatId, "Active: {$conversation->title}");
	}

	private function commandSwitch(int $chatId, ?string $name): void
	{
		if (! $name) {
			$this->trySendMessage($chatId, 'Usage: /switch conversation name');

			return;
		}

		$matches = $this->user->conversations()
			->where('title', 'like', "%{$name}%")
			->orderByDesc('updated_at')
			->get(['id', 'title']);

		if ($matches->isEmpty()) {
			$this->trySendMessage($chatId, "No conversation matching \"{$name}\".");

			return;
		}

		if ($matches->count() > 1) {
			$list = $matches->map(fn ($c, $i) => ($i + 1) . ". {$c->title}")->implode("\n");
			$this->trySendMessage($chatId, "Multiple matches:\n\n{$list}\n\nBe more specific.");

			return;
		}

		$this->activeConversationId = $matches->first()->id;
		$this->trySendMessage($chatId, "Switched to: {$matches->first()->title}");
	}


	private function handleChat(int $chatId, string $text, ?string $image = null): void
	{
		$this->resolveConversation();

		if (! $this->activeConversationId) {
			$this->commandNew($chatId);
		}

		$conversation = $this->assistantUser->conversations()->find($this->activeConversationId);

		if (! $conversation) {
			$this->commandNew($chatId);
			$conversation = $this->assistantUser->conversations()->find($this->activeConversationId);
		}

		$this->telegram->sendTypingAction($chatId);

		$conversation->messages()->create([
			'role' => 'user',
			'content' => $text,
		]);

		$history = $conversation->messages()
			->orderBy('created_at')
			->get(['role', 'content'])
			->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
			->toArray();

		if ($image && count($history) > 0) {
			$lastIndex = array_key_last($history);
			$history[$lastIndex]['images'] = [$image];
		}

		// Load prompt from assistant
		$director = new PromptDirector($this->assistant->prompt);
		$archive = $this->user->archives()->first();

		if ($archive && !empty($text)) {
			$director->withRetrieval($text, $archive->id);
		}

		$systemPrompt = $director
			->except(['emotion tags', 'opening_message'])
			->build();

		try {
			$llm = (new LlmManager())->forAssistantUser($this->assistantUser);
			$response = $llm->chat([
				['role' => 'system', 'content' => $systemPrompt],
				...$history,
			]);
		} catch (\Throwable $e) {
			$this->error("LLM error: {$e->getMessage()}");
			Log::error('Telegram LLM request failed', [
				'chat_id' => $chatId,
				'conversation_id' => $conversation->id,
				'exception' => $e->getMessage(),
			]);

			$this->trySendMessage($chatId, 'Connection failed. Try again.');

			return;
		}

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

		if ($conversation->title === 'New conversation') {
			$conversation->update([
				'title' => str($text)->limit(50)->toString(),
			]);
		}

		$this->trySendMessage($chatId, $content);
		$this->info("Processed. Emotion: {$emotion}");
		Log::info('Telegram message processed', ['chat_id' => $chatId, 'emotion' => $emotion]);
	}

	/**
	 * Send a reply to the chat, falling back to plain text if Markdown parsing fails,
	 * and logging (rather than throwing) if delivery ultimately fails so the poll loop keeps running.
	 */
	private function trySendMessage(int $chatId, string $text): void
	{
		try {
			$this->telegram->sendMessage($chatId, $text);
		} catch (TelegramApiException $e) {
			$this->error("Telegram sendMessage failed: {$e->getMessage()}");
			Log::error('Telegram sendMessage failed, retrying as plain text', [
				'chat_id' => $chatId,
				'error_code' => $e->errorCode,
				'exception' => $e->getMessage(),
			]);

			try {
				$this->telegram->sendMessage($chatId, $text, parseMode: null);
			} catch (TelegramApiException $retryException) {
				$this->error("Telegram sendMessage plain-text retry failed: {$retryException->getMessage()}");
				Log::error('Telegram sendMessage plain-text retry failed', [
					'chat_id' => $chatId,
					'error_code' => $retryException->errorCode,
					'exception' => $retryException->getMessage(),
				]);
			}
		}
	}

	/**
	 * Falls back to the most recently updated conversation if none is active.
	 */
	private function resolveConversation(): void
	{
		if ($this->activeConversationId) {
			return;
		}

		$latest = $this->assistantUser->conversations()
			->orderByDesc('updated_at')
			->first();

		if ($latest) {
			$this->activeConversationId = $latest->id;
		}
	}
}