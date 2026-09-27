<?php

namespace App\Services\LlmProviders;

use App\Builders\ParameterBuilder;
use App\Contracts\LlmProvider;
use App\DTOs\LlmResponse;
use App\DTOs\ToolCallRequest;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use stdClass;

class GeminiProvider implements LlmProvider
{
    /**
     * Callers pass OpenAI-style option names; Gemini rejects unknown generationConfig fields.
     *
     * @var array<string, string>
     */
    private const array OPTION_KEYS = [
        'max_tokens' => 'maxOutputTokens',
        'temperature' => 'temperature',
    ];

    /**
     * @param  array<string, mixed>  $generationConfig
     */
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly string $key,
        private readonly array $generationConfig = [],
        private readonly int $timeout = 120,
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

        $timeout = $params['timeout'] ?? config('ai.default.config.timeout', 120);
        unset($params['timeout']);

        return new static(
            url: rtrim($provider->url, '/'),
            model: $aiModel->endpoint,
            key: $provider->api_key ?? '',
            generationConfig: $params,
            timeout: (int) $timeout,
        );
    }

    public function chat(array $messages, array $options = [], array $tools = []): LlmResponse
    {
        $body = ['contents' => $this->formatContents($messages)];

        $systemInstruction = $this->systemInstruction($messages);

        if ($systemInstruction !== null) {
            $body['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        $generationConfig = [...$this->generationConfig, ...$this->translateOptions($options)];

        if ($generationConfig !== []) {
            $body['generationConfig'] = $generationConfig;
        }

        if (! empty($tools)) {
            $body['tools'] = [[
                'functionDeclarations' => array_map(fn (array $tool) => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parametersJsonSchema' => $tool['parameters'],
                ], $tools),
            ]];
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders(['x-goog-api-key' => $this->key])
            ->post("{$this->url}/models/{$this->model}:generateContent", $body);

        if ($response->failed()) {
            Log::error('[GeminiProvider] LLM request failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('LLM request failed: '.$response->body());
        }

        return $this->parseResponse($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function translateOptions(array $options): array
    {
        $translated = [];

        foreach ($options as $key => $value) {
            if (isset(self::OPTION_KEYS[$key])) {
                $translated[self::OPTION_KEYS[$key]] = $value;
            }
        }

        return $translated;
    }

    /**
     * Gemini accepts a single system instruction, so every system turn is joined into one.
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function systemInstruction(array $messages): ?string
    {
        $systemPrompts = array_filter(
            array_map(fn (array $message) => $message['role'] === 'system' ? $message['content'] : null, $messages),
            fn (?string $content) => filled($content),
        );

        return $systemPrompts === [] ? null : implode("\n\n", $systemPrompts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array{role: string, parts: array<int, array<string, mixed>>}>
     */
    private function formatContents(array $messages): array
    {
        $contents = [];
        $toolNamesByCallId = [];
        $previousRole = null;

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                continue;
            }

            foreach ($message['tool_calls'] ?? [] as $toolCall) {
                $toolNamesByCallId[$toolCall['id']] = $toolCall['name'];
            }

            if ($message['role'] === 'tool') {
                $part = $this->formatToolResultPart($message, $toolNamesByCallId);

                // Gemini requires the results of parallel calls to arrive together in one turn.
                if ($previousRole === 'tool') {
                    $contents[array_key_last($contents)]['parts'][] = $part;
                } else {
                    $contents[] = ['role' => 'user', 'parts' => [$part]];
                }
            } else {
                $contents[] = [
                    'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => $this->formatParts($message),
                ];
            }

            $previousRole = $message['role'];
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<int, array<string, mixed>>
     */
    private function formatParts(array $message): array
    {
        $parts = [];

        if (filled($message['content'] ?? null)) {
            $parts[] = ['text' => $message['content']];
        }

        foreach ($message['images'] ?? [] as $image) {
            $parts[] = ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $image]];
        }

        foreach ($message['tool_calls'] ?? [] as $toolCall) {
            $part = [
                'functionCall' => [
                    'id' => $toolCall['id'],
                    'name' => $toolCall['name'],
                    'args' => $toolCall['arguments'] ?: new stdClass,
                ],
            ];

            if (filled($toolCall['thoughtSignature'] ?? null)) {
                $part['thoughtSignature'] = $toolCall['thoughtSignature'];
            }

            $parts[] = $part;
        }

        return $parts === [] ? [['text' => '']] : $parts;
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, string>  $toolNamesByCallId
     * @return array{functionResponse: array{id: string, name: string, response: array<string, mixed>}}
     */
    private function formatToolResultPart(array $message, array $toolNamesByCallId): array
    {
        $content = $message['content'] ?? '';
        $decoded = json_decode($content, true);

        $response = is_array($decoded) && array_key_exists('error', $decoded)
            ? ['error' => $decoded['error']]
            : ['output' => $decoded ?? $content];

        return [
            'functionResponse' => [
                'id' => $message['tool_call_id'],
                'name' => $toolNamesByCallId[$message['tool_call_id']] ?? $message['tool_call_id'],
                'response' => $response,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parseResponse(array $data): LlmResponse
    {
        if (isset($data['promptFeedback']['blockReason']) && empty($data['candidates'])) {
            throw new \RuntimeException("LLM error: Gemini blocked the prompt ({$data['promptFeedback']['blockReason']}).");
        }

        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];
        $finishReason = $candidate['finishReason'] ?? null;

        if ($parts === [] && $finishReason !== null && $finishReason !== 'STOP') {
            throw new \RuntimeException("LLM error: Gemini returned no content (finish reason: {$finishReason}).");
        }

        $content = '';
        $thinking = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCallRequest(
                    id: $part['functionCall']['id'] ?? 'call_'.Str::random(24),
                    name: $part['functionCall']['name'],
                    arguments: $part['functionCall']['args'] ?? [],
                    thoughtSignature: $part['thoughtSignature'] ?? null,
                );
            } elseif (($part['thought'] ?? false) === true) {
                $thinking .= $part['text'] ?? '';
            } else {
                $content .= $part['text'] ?? '';
            }
        }

        return new LlmResponse(
            content: $content,
            thinking: $thinking !== '' ? $thinking : null,
            toolCalls: $toolCalls,
        );
    }
}
