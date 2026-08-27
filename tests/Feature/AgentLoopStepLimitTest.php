<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a task exceeding the step limit stops and returns a clear partial result', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    $assistant->update(['agent_config' => ['step_limit' => 2]]);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'get_current_datetime', []))
            ->push(toolCallResponse('call_2', 'get_current_datetime', []))
            ->push(finalAnswerResponse('I made some progress but could not finish in time.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'Do something that takes a while.']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('I made some progress but could not finish in time.');

    expect($conversation->messages()->where('role', 'tool_call')->count())->toBe(2);
    Http::assertSentCount(3);
});

test('the step limit does not trigger an error response', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    $assistant->update(['agent_config' => ['step_limit' => 1]]);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'get_current_datetime', []))
            ->push(finalAnswerResponse('Ran out of steps, here is what I found.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'Do something.']]],
    );

    $response->assertSuccessful();
    $response->assertJsonMissing(['message']);
});
