<?php

namespace App\Services\ImageGenProviders;

use App\Contracts\ImageGenProvider;
use App\DTOs\ImageGenResult;
use App\Models\ImageGenModel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenRouterImageGenProvider implements ImageGenProvider
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
            timeout: ($imageGenModel->config ?? [])['timeout'] ?? 120,
            additionalConfig: $imageGenModel->additional_config ?? [],
        );
    }

    public function generate(string $prompt, array $options = []): ImageGenResult
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers())
                ->post($this->url, $this->payload($prompt, $options));
        } catch (ConnectionException $e) {
            throw new RuntimeException('Failed to connect to image generation provider: '.$e->getMessage());
        }

        return $this->parseResponse($response, $prompt);
    }

    public function generateMany(array $prompts, array $options = []): array
    {
        $headers = $this->headers();
        $prompts = array_values($prompts);

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $prompt) => $pool->timeout($this->timeout)
                ->withHeaders($headers)
                ->post($this->url, $this->payload($prompt, $options)),
            $prompts
        ));

        // $responses is keyed by request index (0, 1, ...), but Http::pool()
        // populates it in whichever order the requests actually complete —
        // not the order they were sent — so its own iteration order can
        // differ from $prompts'. Looking each one up by its explicit key
        // (rather than zipping the two arrays positionally, e.g. via
        // array_map with two arrays) is what keeps each response paired
        // with the prompt that produced it.
        $results = [];
        foreach ($prompts as $index => $prompt) {
            $results[] = $this->parseResponse($responses[$index], $prompt);
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        return $headers;
    }

    private function payload(string $prompt, array $options): array
    {
        return array_merge([
            'model' => $this->model,
            'prompt' => $prompt,
        ], $this->additionalConfig, $options);
    }

    private function parseResponse(Response|Throwable $response, string $prompt): ImageGenResult
    {
        if ($response instanceof Throwable) {
            throw new RuntimeException('Failed to connect to image generation provider: '.$response->getMessage());
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
            contentType: $image['media_type'] ?? 'image/png',
            enhancedPrompt: $prompt,
        );
    }
}
