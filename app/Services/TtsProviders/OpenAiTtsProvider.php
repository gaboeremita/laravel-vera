<?php

namespace App\Services\TtsProviders;

use App\Contracts\TtsProvider;
use App\DTOs\VoiceModeResult;
use App\Models\VoiceModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiTtsProvider implements TtsProvider
{
	public function __construct(
		private readonly string $url,
		private readonly string $model,
		private readonly ?string $apiKey,
		private readonly ?string $defaultVoice,
		private readonly int $timeout,
		private readonly ?string $baseInstructions = null,
	) {}

	public static function fromModel(VoiceModel $voiceModel): static
	{
		$provider = $voiceModel->provider;

		return new static(
			url: $provider->url,
			model: $voiceModel->endpoint,
			apiKey: $provider->api_key,
			defaultVoice: $voiceModel->voices[0] ?? null,
			timeout: $voiceModel->config['timeout'] ?? 30,
			baseInstructions: $voiceModel->prompt['tts instructions'] ?? null,
		);
	}

	public function synthesize(string $text, ?string $voice = null, array $options = []): string
	{
		$headers = [];
		if ($this->apiKey) {
			$headers['Authorization'] = "Bearer {$this->apiKey}";
		}

		$payload = [
			'model' => $this->model,
			'input' => $text,
			'voice' => $voice ?? $this->defaultVoice,
		];

		if (! empty($options['instructions'])) {
			$payload['instructions'] = $options['instructions'];
		}

		try {
			$response = Http::timeout($this->timeout)
				->withHeaders($headers)
				->post($this->url, $payload);
		} catch (ConnectionException $e) {
			throw new RuntimeException('Failed to connect to TTS provider: '.$e->getMessage());
		}

		if ($response->failed()) {
			throw new RuntimeException('TTS synthesis request failed: '.$response->body());
		}

		return $response->body();
	}

	public function parseLlmResponse(string $content): VoiceModeResult
	{
		$emotion = null;
		if (preg_match('/\[([a-zA-Z]+)\]/', $content, $matches)) {
			$emotion = $matches[1];
		}

		return new VoiceModeResult(
			content: $content,
			ttsInstructions: $this->buildInstructions($emotion),
		);
	}

	private function buildInstructions(?string $emotion): ?string
	{
		$parts = array_filter([
			$this->baseInstructions,
			$emotion ? "Emotion: {$emotion}" : null,
		]);

		return $parts ? implode("\n", $parts) : null;
	}

	public function llmOptions(): array
	{
		return [];
	}
}
