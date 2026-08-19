<?php

namespace App\Services\ImageGenProviders;

use App\Contracts\ImageGenProvider;
use App\DTOs\ImageGenResult;
use App\Models\ImageGenModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleImageGenProvider implements ImageGenProvider
{
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly ?string $apiKey,
        private readonly int $timeout,
        private readonly array $additionalConfig = [],
    ) {}

    public static function fromModel(ImageGenModel $imageGenModel): static
    {
        $provider = $imageGenModel->provider;

        return new static(
            url: $provider->url,
            model: $imageGenModel->endpoint,
            apiKey: $provider->api_key,
            timeout: $imageGenModel->config['timeout'] ?? 120,
            additionalConfig: $imageGenModel->additional_config ?? [],
        );
    }

    public function generate(string $prompt, array $options = []): ImageGenResult
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        $payload = array_merge([
            'model' => $this->model,
            'prompt' => $prompt,
        ], $this->additionalConfig, $options);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->post($this->url, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Failed to connect to image generation provider: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new RuntimeException('Image generation request failed: '.$response->body());
        }

        $image = $response->json('data.0');

        if (! $image || empty($image['b64_json'])) {
            throw new RuntimeException('Image generation response did not contain an image.');
        }

        return new ImageGenResult(
            imageData: $image['b64_json'],
            contentType: 'image/png',
            enhancedPrompt: $prompt,
        );
    }
}
