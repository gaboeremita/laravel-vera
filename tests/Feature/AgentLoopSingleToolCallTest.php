<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a single get_current_datetime call is incorporated into the final answer', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'get_current_datetime', []))
            ->push(finalAnswerResponse('Today is a great day.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'What is today\'s date?']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Today is a great day.');

    $toolCallMessage = $conversation->messages()->where('role', 'tool_call')->first();
    expect($toolCallMessage)->not->toBeNull();
    expect($toolCallMessage->tool_calls[0]['name'])->toBe('get_current_datetime');
});

test('a single basic_calculator call is incorporated into the final answer', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'basic_calculator', ['expression' => '78 * 3']))
            ->push(finalAnswerResponse('78 times 3 is 234.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'What is 78 times 3?']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('78 times 3 is 234.');
});

test('a task needing no tool is answered directly', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('I think pineapple on pizza is fine.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'What do you think about pineapple on pizza?']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('I think pineapple on pizza is fine.');

    Http::assertSentCount(1);
});

test('a non-agent-mode assistant is entirely unaffected', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant(mode: 'assistant');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'Hello']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Just a normal reply.');

    Http::assertSentCount(1);
    expect($conversation->messages()->whereNotNull('tool_calls')->exists())->toBeFalse();
});
