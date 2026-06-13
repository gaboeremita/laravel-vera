<?php

namespace App\Services\LlmProviders;

use App\Contracts\LlmProvider;
use App\DTOs\LlmResponse;
use Illuminate\Support\Facades\Http;

class OpenRouterProvider implements LlmProvider
{
	public function __construct(
		private readonly string $url,
		private readonly string $model,
		private readonly string $key,
		private readonly int $timeout,
		private readonly bool $stream,
		private readonly int $maxTokens,
		private readonly bool $reasoningEnabled,
		private readonly int $reasoningMaxTokens,
	) {}

	public function chat(array $messages): LlmResponse
	{
		$formattedMessages = array_map([$this, 'formatMessage'], $messages);

		$payload = [
			'model' => $this->model,
			'stream' => $this->stream,
			'max_tokens' => $this->maxTokens,
			'messages' => $formattedMessages,
		];

		if ($this->reasoningEnabled) {
			$payload['reasoning'] = [
				'max_tokens' => $this->reasoningMaxTokens,
			];
		}

		$response = Http::timeout($this->timeout)
			->withHeaders([
				'Authorization' => "Bearer {$this->key}",
			])
			->post("{$this->url}/chat/completions", $payload);

		if ($response->failed()) {
			throw new \RuntimeException('OpenRouter service unavailable');
		}

		$data = $response->json();
		$choice = $data['choices'][0]['message'] ?? [];

		return new LlmResponse(
			content: $choice['content'] ?? '',
			thinking: $choice['reasoning'] ?? null,
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
				'type' => 'image_url',
				'image_url' => ['url' => "data:image/jpeg;base64,{$image}"],
			];
		}

		return [
			'role' => $message['role'],
			'content' => $parts,
		];
	}
}