<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @return array<int, string>
 */
test('the background tool is offered to 3D avatar assistants in agent mode', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('agent', ['portrait_type' => 'avatar3d']);

    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello.'))]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'hello']],
    )->assertSuccessful();

    expect(offeredToolNames())->toContain('change_background');
});

test('the background tool is left out for assistants without a 3D avatar', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello.'))]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'hello']],
    )->assertSuccessful();

    expect(offeredToolNames())->not->toContain('change_background');
});

test('calling the background tool queues a background for the described setting', function () {
    Queue::fake();
    [$user, $assistant, $conversation] = setUpAgentAssistant('agent', ['portrait_type' => 'avatar3d']);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'change_background', ['description' => 'the library']))
            ->push(finalAnswerResponse('There, the library.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'change the background to the library']],
    )->assertSuccessful();

    Queue::assertPushed(GenerateAvatarBackground::class, fn (GenerateAvatarBackground $job) => $job->description === 'the library' && $job->conversation->is($conversation));
});
