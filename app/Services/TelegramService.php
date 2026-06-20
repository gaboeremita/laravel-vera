<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
			throw new \RuntimeException('Telegram getUpdates failed: ' . $response->body());
		}

		$data = $response->json();

		if (! ($data['ok'] ?? false)) {
			throw new \RuntimeException('Telegram API error: ' . ($data['description'] ?? 'Unknown'));
		}

		return $data['result'] ?? [];
	}

	/**
	 * Send a text message to a chat.
	 */
	public function sendMessage(int $chatId, string $text): void
	{
		$response = Http::post("{$this->baseUrl}/sendMessage", [
			'chat_id' => $chatId,
			'text' => $text,
			'parse_mode' => 'Markdown',
		]);

		if ($response->failed()) {
			throw new \RuntimeException('Telegram sendMessage failed: ' . $response->body());
		}
	}

	/**
	 * Show "typing..." indicator in the chat.
	 */
	public function sendTypingAction(int $chatId): void
	{
		Http::post("{$this->baseUrl}/sendChatAction", [
			'chat_id' => $chatId,
			'action' => 'typing',
		]);
	}

	/**
	 * Get the file path for a file_id from Telegram.
	 */
	public function getFilePath(string $fileId): string
	{
		$response = Http::post("{$this->baseUrl}/getFile", [
			'file_id' => $fileId,
		]);

		if ($response->failed()) {
			throw new \RuntimeException('Telegram getFile failed: ' . $response->body());
		}

		return $response->json('result.file_path');
	}

	/**
	 * Download a file from Telegram and return its base64-encoded content.
	 */
	public function downloadFileAsBase64(string $filePath): string
	{
		$fileUrl = "{$this->url}/file/bot{$this->token}/{$filePath}";
		$response = Http::get($fileUrl);

		if ($response->failed()) {
			throw new \RuntimeException('Telegram file download failed.');
		}

		return base64_encode($response->body());
	}
}