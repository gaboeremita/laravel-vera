<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a generate_image call with an empty prompt is rejected without generating an image', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'generate_image', ['prompt' => '']))
            ->push(finalAnswerResponse('I need a description of what to generate.')),
        'openrouter.ai/*' => Http::response(imageGenHttpResponse()),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'generate an image']]],
    );

    $response->assertSuccessful();
    expect($response->json('tool_calls.0.name'))->toBe('generate_image');
    expect($response->json('tool_calls.0.error'))->not->toBeNull();

    expect($conversation->messages()->where('role', 'assistant')->whereHas('image')->exists())->toBeFalse();
    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'openrouter.ai')))->toHaveCount(0);
});
