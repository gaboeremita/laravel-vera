<?php

use App\Contracts\AgentTool;
use App\Models\AiModel;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a tool call that exceeds agent.tool_timeout is interrupted, not left to run to completion', function () {
    config(['agent.tool_timeout' => 1, 'agent.tool_retry_attempts' => 2]);

    [, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'slow_tool', []))
            ->push(finalAnswerResponse('Gave up on the slow tool.')),
    ]);

    $slowTool = new class implements AgentTool
    {
        public function name(): string
        {
            return 'slow_tool';
        }

        public function description(): string
        {
            return 'A tool that deliberately sleeps past the configured timeout.';
        }

        public function parameters(): array
        {
            return ['type' => 'object', 'properties' => new stdClass];
        }

        public function handle(array $arguments): array
        {
            sleep(5);

            return ['result' => 'should never get here'];
        }

        public function timeoutSeconds(): int
        {
            return config('agent.tool_timeout');
        }

        public function retryAttempts(): int
        {
            return config('agent.tool_retry_attempts');
        }
    };

    $runner = new AgentLoopRunner(
        (new LlmManager)->fromModel(
            AiModel::with('provider')->first()
        ),
        [$slowTool],
    );

    $start = microtime(true);
    $result = $runner->run(
        assistant: $assistant,
        messages: [['role' => 'user', 'content' => 'Use the slow tool.']],
        conversation: $conversation,
    );
    $elapsed = microtime(true) - $start;

    // 2 attempts * 1s timeout each ≈ 2s, nowhere near 2 * 5s = 10s if the sleep()
    // calls had been allowed to run to completion uninterrupted.
    expect($elapsed)->toBeLessThan(4.0);

    // Both attempts within this one step timed out (executeWithRetries exhausted
    // its 2 attempts), but that's only 1 *failed step* overall — below the
    // consecutive-failure give-up threshold (also 2) — so the loop correctly
    // gives the model another turn instead of short-circuiting with FR-014's
    // hardcoded message.
    expect($result->content)->toBe('Gave up on the slow tool.');

    $failedCall = $conversation->messages()->where('role', 'tool_call')->latest('id')->first();
    expect($failedCall->tool_calls[0]['error'])->toContain('timed out after 1 seconds');
});
