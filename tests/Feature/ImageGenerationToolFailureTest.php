<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a failing image generation surfaces a clear failure instead of hanging', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'generate_image', ['prompt' => 'a cat']))
            ->push(finalAnswerResponse('a detailed, enhanced description of a cat'))
            ->push(finalAnswerResponse('Sorry, something went wrong generating that image.')),
        'openrouter.ai/*' => Http::response(['message' => 'upstream failure'], 502),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'show me a cat']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Sorry, something went wrong generating that image.');
    expect($response->json('tool_calls.0.name'))->toBe('generate_image');
    expect($response->json('tool_calls.0.error'))->not->toBeNull();

    expect($conversation->messages()->where('role', 'assistant')->whereHas('image')->exists())->toBeFalse();

    // retryAttempts() = 1 for this tool (research.md #5) — no retry against the paid provider.
    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'openrouter.ai')))->toHaveCount(1);
});
