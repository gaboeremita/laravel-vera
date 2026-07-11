<?php

namespace App\Services\LlmProviders;

use App\Contracts\LlmProvider;
use App\DTOs\LlmResponse;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;

class GenericProvider implements LlmProvider
{
	public function __construct(
		private readonly string $url,
		private readonly string $model,
		private readonly ?string $apiKey = null,
		private readonly bool $thinking = false,
		private readonly ?string $thinkingKey = null,
		private readonly array $config = [],
	) {}

	public static function fromModel(AiModel $aiModel): static
	{
		$provider = $aiModel->provider;
		$schema = $provider->config_schema ?? [];

		return new static(
			url: $provider->url,
			model: $aiModel->name,
			apiKey: $provider->api_key,
			thinking: $aiModel->thinking,
			thinkingKey: $schema['thinking_key'] ?? null,
			config: $aiModel->config ?? [],
		);
	}

	public function chat(array $messages): LlmResponse
	{
		$formattedMessages = array_map([$this, 'formatMessage'], $messages);

		$payload = [
			'model' => $this->model,
			'stream' => config('ai.stream', false),
			'messages' => $formattedMessages,
		];

		if (isset($this->config['max_tokens'])) {
			$payload['max_tokens'] = $this->config['max_tokens'];
		}

		if ($this->thinking && isset($this->config['thinking_budget'])) {
			$payload['reasoning'] = [
				'max_tokens' => $this->config['thinking_budget'],
			];
		}

		$headers = [];
		if ($this->apiKey) {
			$headers['Authorization'] = "Bearer {$this->apiKey}";
		}

		$timeout = $this->config['timeout'] ?? config('ai.defaults.timeout', 600);

		$response = Http::timeout($timeout)
			->withHeaders($headers)
			->post($this->url, $payload);

		if ($response->failed()) {
			throw new \RuntimeException('LLM request failed: ' . $response->body());
		}

		$data = $response->json();

		if (isset($data['error'])) {
			throw new \RuntimeException('LLM error: ' . ($data['error']['message'] ?? 'Unknown error'));
		}

		$choice = $data['choices'][0]['message'] ?? [];
		$thinkingKey = $this->thinkingKey ?? 'reasoning';

		return new LlmResponse(
			content: $choice['content'] ?? '',
			thinking: $choice[$thinkingKey] ?? null,
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