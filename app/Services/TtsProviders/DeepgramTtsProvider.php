<?php

namespace App\Services\TtsProviders;

use App\Contracts\TtsProvider;
use App\DTOs\VoiceModeResult;
use App\Models\VoiceModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepgramTtsProvider implements TtsProvider
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $apiKey,
        private readonly ?string $defaultVoice,
        private readonly int $timeout,
    ) {}

    public static function fromModel(VoiceModel $voiceModel): static
    {
        $provider = $voiceModel->provider;

        return new static(
            url: rtrim($provider->url, '/').'/'.ltrim($voiceModel->endpoint, '/'),
            apiKey: $provider->api_key,
            defaultVoice: $voiceModel->voices[0] ?? null,
            timeout: (int) data_get($voiceModel->config, 'timeout', 30),
        );
    }

    public function synthesize(string $text, ?string $voice = null, array $options = []): string
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = "Token {$this->apiKey}";
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->post($this->url.'?model='.($voice ?? $this->defaultVoice), ['text' => $text]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Failed to connect to TTS provider: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new RuntimeException('TTS synthesis request failed: '.$response->body());
        }

        return $response->body();
    }

    public function contentType(): string
    {
        return 'audio/mpeg';
    }

    public function parseLlmResponse(string $content): VoiceModeResult
    {
        return new VoiceModeResult(content: $content);
    }

    public function llmOptions(): array
    {
        return [];
    }
}
