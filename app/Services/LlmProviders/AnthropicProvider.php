<?php

namespace App\Services\LlmProviders;

use App\Builders\ParameterBuilder;
use App\Contracts\LlmProvider;
use App\DTOs\LlmResponse;
use App\DTOs\ToolCallRequest;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements LlmProvider
{
    private const string DEFAULT_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly string $key,
        private readonly string $version,
        private readonly array $params = [],
    ) {}

    public static function fromModel(AiModel $aiModel): static
    {
        $provider = $aiModel->provider;
        $config = $aiModel->config ?? [];

        $params = (new ParameterBuilder)->build(
            schema: $provider->config_schema ?? [],
            config: $config,
        );

        if (! empty($aiModel->additional_config)) {
            $params = array_merge($params, $aiModel->additional_config);
        }

        return new static(
            url: $provider->url,
            model: $aiModel->endpoint,
            key: $provider->api_key,
            version: $config['version'] ?? self::DEFAULT_VERSION,
            params: $params,
        );
    }

    public function chat(array $messages, array $options = [], array $tools = []): LlmResponse
    {
        $systemPrompt = null;
        $chatMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemPrompt = $message['content'];
            } else {
                $chatMessages[] = $this->formatMessage($message);
            }
        }

        $body = [
            'model' => $this->model,
            'stream' => config('ai.stream', false),
            'messages' => $chatMessages,
            ...$this->params,
        ];

        if ($systemPrompt) {
            $body['system'] = $systemPrompt;
        }

        if (! empty($tools)) {
            $body['tools'] = array_map(fn (array $tool) => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $tool['parameters'],
            ], $tools);
        }

        $response = Http::timeout(config('ai.default.config.timeout', 120))
            ->withHeaders([
                'x-api-key' => $this->key,
                'anthropic-version' => $this->version,
            ])
            ->post("{$this->url}/messages", $body);

        if ($response->failed()) {
            throw new \RuntimeException($response->body());
        }

        $data = $response->json();

        $content = '';
        $thinking = null;
        $toolCalls = [];

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content = $block['text'];
            } elseif ($block['type'] === 'thinking') {
                $thinking = $block['thinking'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = new ToolCallRequest(
                    id: $block['id'],
                    name: $block['name'],
                    arguments: $block['input'],
                );
            }
        }

        return new LlmResponse(
            content: $content,
            thinking: $thinking,
            toolCalls: $toolCalls,
        );
    }

    private function formatMessage(array $message): array
    {
        if ($message['role'] === 'tool') {
            $content = $message['content'] ?? '';
            $decoded = json_decode($content, true);
            $isError = is_array($decoded) && array_key_exists('error', $decoded);

            return [
                'role' => 'user',
                'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => $message['tool_call_id'],
                    'content' => $isError ? (string) ($decoded['error'] ?? $content) : $content,
                    'is_error' => $isError,
                ]],
            ];
        }

        if (! empty($message['tool_calls'])) {
            $parts = [];

            if (! empty($message['content'])) {
                $parts[] = ['type' => 'text', 'text' => $message['content']];
            }

            foreach ($message['tool_calls'] as $toolCall) {
                $parts[] = [
                    'type' => 'tool_use',
                    'id' => $toolCall['id'],
                    'name' => $toolCall['name'],
                    'input' => $toolCall['arguments'],
                ];
            }

            return [
                'role' => 'assistant',
                'content' => $parts,
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
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'image/jpeg',
                    'data' => $image,
                ],
            ];
        }

        return [
            'role' => $message['role'],
            'content' => $parts,
        ];
    }
}
