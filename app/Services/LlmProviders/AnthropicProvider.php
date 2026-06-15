<?php

namespace App\Services\LlmProviders;

use App\Contracts\LlmProvider;
use App\DTOs\LlmResponse;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements LlmProvider
{
	public function __construct(
		private readonly string $url,
		private readonly string $model,
		private readonly string $key,
		private readonly string $version,
		private readonly int $timeout,
		private readonly bool $stream,
		private readonly int $maxTokens,
		private readonly bool $thinkingEnabled,
		private readonly int $thinkingBudget,
	) {}

	public function chat(array $messages): LlmResponse
	{
		$systemPrompt = null;
		$chatMessages = [];

		foreach ($messages as $message) {
			if ($message['role'] === 'system') {
				$systemPrompt = $message['content'];
			} else {
				$chatMessages[] = $this->formatMessage($message);
			}
		}

		$body = [
			'model' => $this->model,
			'max_tokens' => $this->maxTokens,
			'stream' => $this->stream,
			'messages' => $chatMessages,
		];

		if ($systemPrompt) {
			$body['system'] = $systemPrompt;
		}

		if ($this->thinkingEnabled) {
			$body['thinking'] = [
				'type' => 'enabled',
				'budget_tokens' => $this->thinkingBudget,
			];
		}

		$response = Http::timeout($this->timeout)
			->withHeaders([
				'x-api-key' => $this->key,
				'anthropic-version' => $this->version,
			])
			->post("{$this->url}/messages", $body);

		if ($response->failed()) {
			throw new \RuntimeException($response->body());
		}

		$data = $response->json();

		$content = '';
		$thinking = null;

		foreach ($data['content'] ?? [] as $block) {
			if ($block['type'] === 'text') {
				$content = $block['text'];
			} elseif ($block['type'] === 'thinking') {
				$thinking = $block['thinking'];
			}
		}

		return new LlmResponse(
			content: $content,
			thinking: $thinking,
		);
	}

	private function formatMessage(array $message): array
	{
		if (empty($message['images'])) {
			return [
				'role' => $message['role'],
				'content' => $message['content'] ?? '',
			];
		}

		$parts = [];

		if (! empty($message['content'])) {
			$parts[] = ['type' => 'text', 'text' => $message['content']];
		}

		foreach ($message['images'] as $image) {
			$parts[] = [
				'type' => 'image',
				'source' => [
					'type' => 'base64',
					'media_type' => 'image/jpeg',
					'data' => $image,
				],
			];
		}

		return [
			'role' => $message['role'],
			'content' => $parts,
		];
	}
}