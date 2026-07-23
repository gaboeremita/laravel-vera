<?php

namespace App\Services\TtsProviders;

use App\Contracts\TtsProvider;
use App\Models\VoiceModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleTtsProvider implements TtsProvider
{
	public function __construct(
		private readonly string $url,
		private readonly string $model,
		private readonly ?string $apiKey,
		private readonly ?string $defaultVoice,
		private readonly int $timeout,
	) {}

	public static function fromModel(VoiceModel $voiceModel): static
	{
		$provider = $voiceModel->provider;

		return new static(
			url: $provider->url,
			model: $voiceModel->endpoint,
			apiKey: $provider->api_key,
			defaultVoice: $voiceModel->voices[0] ?? null,
			timeout: $voiceModel->config['timeout'] ?? 120,
		);
	}

	public function synthesize(string $text, ?string $voice = null): string
	{
		$headers = [];
		if ($this->apiKey) {
			$headers['Authorization'] = "Bearer {$this->apiKey}";
		}

		try {
			$response = Http::timeout($this->timeout)
				->withHeaders($headers)
				->post($this->url, [
					'model' => $this->model,
					'input' => $text,
					'voice' => $voice ?? $this->defaultVoice,
				]);
		} catch (ConnectionException $e) {
			throw new RuntimeException('Failed to connect to TTS provider: '.$e->getMessage());
		}

		if ($response->failed()) {
			throw new RuntimeException('TTS synthesis request failed: '.$response->body());
		}

		return $response->body();
	}
}
