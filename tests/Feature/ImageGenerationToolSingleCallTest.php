<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a single generate_image call produces an image message and completes the task', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'generate_image', ['prompt' => 'a cyberpunk cat']))
            ->push(finalAnswerResponse('a detailed, enhanced description of a cyberpunk cat'))
            ->push(finalAnswerResponse('Here is your cyberpunk cat!')),
        'openrouter.ai/*' => Http::response(imageGenHttpResponse()),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'show me a cyberpunk cat']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Here is your cyberpunk cat!');

    $imageMessage = $conversation->messages()->where('role', 'assistant')->whereHas('image')->first();
    expect($imageMessage)->not->toBeNull();
    expect($imageMessage->content)->toBe('');

    expect($response->json('tool_calls.0.name'))->toBe('generate_image');
    expect($response->json('tool_calls.0.result.status'))->toBe('success');
});
