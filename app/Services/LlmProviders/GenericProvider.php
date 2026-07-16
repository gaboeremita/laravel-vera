<?php

namespace App\Services\LlmProviders;

use App\Builders\ParameterBuilder;
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
        private readonly bool $stream = false,
        private readonly array $params = [],
    ) {}

    public static function fromModel(AiModel $aiModel): static
    {
        $provider = $aiModel->provider;

        $params = (new ParameterBuilder)->build(
            schema: $provider->config_schema ?? [],
            config: $aiModel->config ?? [],
        );

        return new static(
            url: $provider->url,
            model: $aiModel->endpoint,
            apiKey: $provider->api_key,
            stream: config('ai.stream', false),
            params: $params,
        );
    }

    public function chat(array $messages): LlmResponse
    {
        $payload = [
            'model' => $this->model,
            'stream' => $this->stream,
            'messages' => array_map([$this, 'formatMessage'], $messages),
            ...$this->params,
        ];

        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        $response = Http::timeout(config('ai.defaults.timeout', 600))
            ->withHeaders($headers)
            ->post($this->url, $payload);

        if ($response->failed()) {
            throw new \RuntimeException('LLM request failed: '.$response->body());
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new \RuntimeException('LLM error: '.($data['error']['message'] ?? 'Unknown error'));
        }

        $choice = $data['choices'][0]['message'] ?? [];

        return new LlmResponse(
            content: $choice['content'] ?? '',
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
