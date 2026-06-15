<?php

namespace App\Services\LlmProviders;

use App\Contracts\LlmProvider;
use App\DTOs\LlmResponse;
use Illuminate\Support\Facades\Http;

class OllamaProvider implements LlmProvider
{
	public function __construct(
		private readonly string $url,
		private readonly string $model,
		private readonly int $timeout,
		private readonly bool $stream,
		private readonly bool $think,
	) {}

	public function chat(array $messages): LlmResponse
	{
		$response = Http::timeout($this->timeout)
			->post("{$this->url}/api/chat", [
				'model' => $this->model,
				'stream' => $this->stream,
				'think' => $this->think,
				'messages' => $messages,
			]);

		if ($response->failed()) {
			throw new \RuntimeException($response->body());
		}

		$data = $response->json();

		return new LlmResponse(
			content: $data['message']['content'] ?? '',
			thinking: $data['message']['thinking'] ?? null,
		);
	}
}