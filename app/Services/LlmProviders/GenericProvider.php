<?php

namespace App\Services\LlmProviders;

use App\Builders\ParameterBuilder;
use App\Contracts\LlmProvider;
use App\DTOs\LlmResponse;
use App\DTOs\ToolCallRequest;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenericProvider implements LlmProvider
{
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly ?string $apiKey = null,
        private readonly bool $stream = false,
        private readonly array $params = [],
        private readonly ?string $thinkingKey = null,
    ) {}

    public static function fromModel(AiModel $aiModel): static
    {
        $provider = $aiModel->provider;

        $params = (new ParameterBuilder)->build(
            schema: $provider->config_schema ?? [],
            config: $aiModel->config ?? [],
        );

        if (! empty($aiModel->additional_config)) {
            $params = array_merge($params, $aiModel->additional_config);
        }

        return new static(
            url: $provider->url,
            model: $aiModel->endpoint,
            apiKey: $provider->api_key,
            stream: config('ai.stream', false),
            params: $params,
            thinkingKey: $aiModel->thinking_key,
        );
    }

    public function chat(array $messages, array $options = [], array $tools = []): LlmResponse
    {
        $payload = [
            'model' => $this->model,
            'stream' => $this->stream,
            'messages' => array_map([$this, 'formatMessage'], $messages),
            ...$this->params,
            ...$options,
        ];

        if (! empty($tools)) {
            $payload['tools'] = array_map(fn (array $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            ], $tools);
        }

        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        $response = Http::timeout(config('ai.default.config.timeout', 600))
            ->withHeaders($headers)
            ->post($this->url, $payload);

        if ($response->failed()) {
            Log::error('[GenericProvider] LLM request failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('LLM request failed: '.$response->body());
        }

        $data = $response->json();

        if (isset($data['error'])) {
            Log::error('[GenericProvider] LLM error', ['error' => $data['error']]);
            throw new \RuntimeException('LLM error: '.($data['error']['message'] ?? 'Unknown error'));
        }

        $choice = $data['choices'][0]['message'] ?? [];

        $toolCalls = array_map(fn (array $toolCall) => new ToolCallRequest(
            id: $toolCall['id'],
            name: $toolCall['function']['name'],
            arguments: json_decode($toolCall['function']['arguments'], associative: true) ?? [],
        ), $choice['tool_calls'] ?? []);

        return new LlmResponse(
            content: $choice['content'] ?? '',
            thinking: $this->thinkingKey ? ($choice[$this->thinkingKey] ?? null) : null,
            toolCalls: $toolCalls,
        );
    }

    private function formatMessage(array $message): array
    {
        if ($message['role'] === 'tool') {
            return [
                'role' => 'tool',
                'tool_call_id' => $message['tool_call_id'],
                'content' => $message['content'] ?? '',
            ];
        }

        if (! empty($message['tool_calls'])) {
            return [
                'role' => 'assistant',
                'content' => $message['content'] ?? null,
                'tool_calls' => array_map(fn (array $toolCall) => [
                    'id' => $toolCall['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $toolCall['name'],
                        'arguments' => json_encode($toolCall['arguments']),
                    ],
                ], $message['tool_calls']),
            ];
        }

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
