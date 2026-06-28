<?php

namespace App\Providers\Embeddings;

use App\Contracts\EmbeddingProvider;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Illuminate\Support\Facades\Http;


class OllamaEmbeddingProvider implements EmbeddingProvider
{

	public function __construct(
		private readonly string $baseUrl,
		private readonly string $model,
	) {}

	public function embed(string $text): array
	{
		return $this->embedBatch([$text])[0];
	}

	public function embedBatch(array $texts): array
	{
		try {
			$response = Http::baseUrl($this->baseUrl)
				->timeout(30)
				->post('/api/embed', [
					'model' => $this->model,
					'input' => $texts,
				]);
		} catch (ConnectionException $e) {
			throw new RuntimeException('Failed to connect to Ollama: ' . $e->getMessage());
		}

		if ($response->failed()) {
			throw new RuntimeException('Ollama embedding request failed: ' . $response->body());
		}

		$embeddings = $response->json('embeddings');

		if (! $embeddings || count($embeddings) !== count($texts)) {
			throw new RuntimeException('Unexpected embedding response from Ollama');
		}

		return $embeddings;
	}
}