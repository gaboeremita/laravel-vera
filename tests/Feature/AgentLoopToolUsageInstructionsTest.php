<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('every request in the loop tells the model how to treat an already-returned tool result', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'basic_calculator', ['expression' => '1 + 1']))
            ->push(finalAnswerResponse('1 + 1 is 2.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'what is 1 + 1?']]],
    )->assertSuccessful();

    Http::assertSentCount(2);

    Http::assertSent(function ($request) {
        $systemMessage = collect($request['messages'])->firstWhere('role', 'system');

        return $systemMessage
            && str_contains($systemMessage['content'], 'never describe a tool call as text or JSON')
            && str_contains($systemMessage['content'], 'treat that tool as already done');
    });
});

test('the tool-usage instruction is appended to the existing system prompt, not sent as a separate message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'hello']]],
    )->assertSuccessful();

    Http::assertSent(function ($request) {
        $systemMessages = collect($request['messages'])->where('role', 'system');

        return $systemMessages->count() === 1;
    });
});
