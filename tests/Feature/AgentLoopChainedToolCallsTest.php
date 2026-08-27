<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('two dependent tool calls are resolved in one exchange', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'get_current_datetime', []))
            ->push(toolCallResponse('call_2', 'basic_calculator', ['expression' => '26 * 3']))
            ->push(finalAnswerResponse('Today\'s day of the month, tripled, is 78.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => "What's today's day of the month, tripled?"]]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Today\'s day of the month, tripled, is 78.');

    $toolCallMessages = $conversation->messages()->where('role', 'tool_call')->orderBy('id')->get();
    expect($toolCallMessages)->toHaveCount(2);
    expect($toolCallMessages[0]->tool_calls[0]['name'])->toBe('get_current_datetime');
    expect($toolCallMessages[1]->tool_calls[0]['name'])->toBe('basic_calculator');

    Http::assertSentCount(3);
});

test('more than two dependent tool calls are chained until a final answer', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'get_current_datetime', []))
            ->push(toolCallResponse('call_2', 'basic_calculator', ['expression' => '26 * 3']))
            ->push(toolCallResponse('call_3', 'basic_calculator', ['expression' => '78 + 1']))
            ->push(finalAnswerResponse('The final result is 79.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'Chain a few calculations for me.']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('The final result is 79.');
    expect($conversation->messages()->where('role', 'tool_call')->count())->toBe(3);
});
