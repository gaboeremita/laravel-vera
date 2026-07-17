<?php

namespace App\Services;

use App\Exceptions\TelegramApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
	private string $baseUrl;

	public function __construct(
		private readonly string $url,
		private readonly string $token,
	) {
		$this->baseUrl = "{$this->url}/bot{$this->token}";
	}

	/**
	 * Long-poll for new messages. Blocks until a message arrives or timeout expires.
	 *
	 * @return array<int, array>
	 */
	public function getUpdates(int $offset = 0, int $timeout = 30): array
	{
		// HTTP timeout must exceed Telegram's poll timeout to avoid premature disconnect
		$response = Http::timeout($timeout + 5)
			->post("{$this->baseUrl}/getUpdates", [
				'offset' => $offset,
				'timeout' => $timeout,
				'allowed_updates' => ['message'],
			]);

		if ($response->failed()) {
			throw TelegramApiException::fromResponse($response, 'getUpdates');
		}

		$data = $response->json();

		if (! ($data['ok'] ?? false)) {
			throw TelegramApiException::fromResponse($response, 'getUpdates');
		}

		return $data['result'] ?? [];
	}

	/**
	 * Send a text message to a chat.
	 *
	 * @throws TelegramApiException
	 */
	public function sendMessage(int $chatId, string $text, ?string $parseMode = 'Markdown'): void
	{
		$payload = [
			'chat_id' => $chatId,
			'text' => $text,
		];

		if ($parseMode) {
			$payload['parse_mode'] = $parseMode;
		}

		$response = Http::timeout(config('ai.telegram.send_timeout', 15))
			->post("{$this->baseUrl}/sendMessage", $payload);

		if ($response->failed()) {
			throw TelegramApiException::fromResponse($response, 'sendMessage');
		}
	}

	/**
	 * Show "typing..." indicator in the chat. Best-effort only, failures are logged and ignored.
	 */
	public function sendTypingAction(int $chatId): void
	{
		$response = Http::timeout(config('ai.telegram.typing_timeout', 10))->post("{$this->baseUrl}/sendChatAction", [
			'chat_id' => $chatId,
			'action' => 'typing',
		]);

		if ($response->failed()) {
			Log::warning('Telegram sendChatAction failed', [
				'chat_id' => $chatId,
				'response' => $response->body(),
			]);
		}
	}

	/**
	 * Get the file path for a file_id from Telegram.
	 *
	 * @throws TelegramApiException
	 */
	public function getFilePath(string $fileId): string
	{
		$response = Http::timeout(config('ai.telegram.file_timeout', 15))->post("{$this->baseUrl}/getFile", [
			'file_id' => $fileId,
		]);

		if ($response->failed()) {
			throw TelegramApiException::fromResponse($response, 'getFile');
		}

		return $response->json('result.file_path');
	}

	/**
	 * Download a file from Telegram and return its base64-encoded content.
	 */
	public function downloadFileAsBase64(string $filePath): string
	{
		$fileUrl = "{$this->url}/file/bot{$this->token}/{$filePath}";
		$response = Http::timeout(config('ai.telegram.download_timeout', 30))->get($fileUrl);

		if ($response->failed()) {
			throw new \RuntimeException('Telegram file download failed.');
		}

		return base64_encode($response->body());
	}
}
