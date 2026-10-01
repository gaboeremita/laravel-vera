<?php

use App\Models\AiModel;
use App\Models\Settings;
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
        ['message' => ['content' => 'what is 1 + 1?']],
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
        ['message' => ['content' => 'hello']],
    )->assertSuccessful();

    Http::assertSent(function ($request) {
        $systemMessages = collect($request['messages'])->where('role', 'system');

        return $systemMessages->count() === 1;
    });
});

test('with cache marks on, the tool-usage instruction sits inside the cached unchanging part', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    $aiModel = AiModel::find(Settings::where('user_id', $user->id)->first()->data['ai_model_id']);
    $aiModel->update(['cache_marks' => true]);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'hello']],
    )->assertSuccessful();

    Http::assertSent(function ($request) {
        $firstBlock = collect($request['messages'])->firstWhere('role', 'system')['content'][0];

        return str_contains($firstBlock['text'], 'never describe a tool call as text or JSON')
            && $firstBlock['cache_control'] === ['type' => 'ephemeral'];
    });
});
