<?php

namespace App\Services\AgentLoop;

use App\Contracts\AgentTool;
use App\Contracts\LlmProvider;
use App\DTOs\AgentRunResult;
use App\DTOs\ToolCallRequest;
use App\Models\Assistant;
use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AgentLoopRunner
{
    /**
     * @param  AgentTool[]  $tools
     */
    public function __construct(
        private readonly LlmProvider $provider,
        private readonly array $tools,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function run(Assistant $assistant, array $messages, Conversation $conversation): AgentRunResult
    {
        $stepLimit = data_get($assistant->agent_config, 'step_limit', config('agent.step_limit'));
        $maxConsecutiveFailures = config('agent.tool_retry_attempts');
        $toolDefinitions = $this->toolDefinitions();

        $consecutiveFailures = 0;
        $step = 0;
        $toolCallsSummary = [];

        try {
            while ($step < $stepLimit) {
                $response = $this->provider->chat($messages, tools: $toolDefinitions);

                if ($response->isFinal()) {
                    return new AgentRunResult($response->content, $toolCallsSummary);
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $response->content,
                    'tool_calls' => array_map(fn (ToolCallRequest $call) => [
                        'id' => $call->id,
                        'name' => $call->name,
                        'arguments' => $call->arguments,
                    ], $response->toolCalls),
                ];

                foreach ($response->toolCalls as $toolCall) {
                    $step++;
                    $this->writeProgress($conversation->id, "Calling tool: {$toolCall->name}");

                    try {
                        $result = $this->executeWithRetries($toolCall);
                        $consecutiveFailures = 0;

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall->id,
                            'content' => json_encode($result),
                        ];

                        $toolCallsSummary[] = $this->recordToolCall($conversation, $toolCall, result: $result);
                    } catch (\Throwable $e) {
                        $consecutiveFailures++;

                        Log::warning('[AgentLoopRunner] Tool call failed', [
                            'tool' => $toolCall->name,
                            'error' => $e->getMessage(),
                        ]);

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall->id,
                            'content' => json_encode(['error' => $e->getMessage()]),
                        ];

                        $toolCallsSummary[] = $this->recordToolCall($conversation, $toolCall, error: $e->getMessage());

                        if ($consecutiveFailures >= $maxConsecutiveFailures) {
                            return new AgentRunResult(
                                "I wasn't able to complete this task after a few different attempts — {$e->getMessage()}",
                                $toolCallsSummary,
                            );
                        }
                    }

                    if ($step >= $stepLimit) {
                        break;
                    }
                }
            }

            return new AgentRunResult($this->requestFinalSummary($messages), $toolCallsSummary);
        } finally {
            $this->clearProgress($conversation->id);
        }
    }

    /**
     * @return array{name: string, arguments: array<string, mixed>, result: array<string, mixed>|null, error: string|null}
     */
    private function recordToolCall(Conversation $conversation, ToolCallRequest $toolCall, mixed $result = null, ?string $error = null): array
    {
        $entry = [
            'name' => $toolCall->name,
            'arguments' => $toolCall->arguments,
            'result' => $result,
            'error' => $error,
        ];

        $conversation->messages()->create([
            'role' => 'tool_call',
            'tool_calls' => [['id' => $toolCall->id, ...$entry]],
        ]);

        return $entry;
    }

    /**
     * Retries the exact same call up to `agent.tool_retry_attempts` times (FR-012),
     * each attempt bounded by `agent.tool_timeout` (FR-015).
     */
    private function executeWithRetries(ToolCallRequest $toolCall): array
    {
        $tool = $this->findTool($toolCall->name);
        $attempts = config('agent.tool_retry_attempts');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->executeWithTimeout($tool, $toolCall->arguments);
            } catch (\Throwable $e) {
                if ($attempt === $attempts) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Unreachable.');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function executeWithTimeout(AgentTool $tool, array $arguments): array
    {
        $timeout = config('agent.tool_timeout');

        if (! function_exists('pcntl_alarm') || ! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals') || ! function_exists('pcntl_signal_get_handler')) {
            throw new \RuntimeException('Tool call timeout enforcement requires the pcntl extension.');
        }

        $previousHandler = pcntl_signal_get_handler(SIGALRM);

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, function () use ($timeout): never {
            throw new \RuntimeException("Tool call timed out after {$timeout} seconds.");
        });
        pcntl_alarm($timeout);

        try {
            return $tool->handle($arguments);
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previousHandler);
        }
    }

    private function findTool(string $name): AgentTool
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        throw new \RuntimeException("Unknown tool requested: {$name}");
    }

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    private function toolDefinitions(): array
    {
        return array_map(fn (AgentTool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->parameters(),
        ], $this->tools);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function requestFinalSummary(array $messages): string
    {
        $messages[] = [
            'role' => 'user',
            'content' => "You've reached the maximum number of steps allowed for this task. Summarize what you've found or accomplished so far, and explain what's left undone.",
        ];

        return $this->provider->chat($messages)->content;
    }

    private function writeProgress(int $conversationId, string $status): void
    {
        Cache::put("agent-progress:{$conversationId}", $status, config('agent.progress_cache_ttl'));
    }

    private function clearProgress(int $conversationId): void
    {
        Cache::forget("agent-progress:{$conversationId}");
    }
}
