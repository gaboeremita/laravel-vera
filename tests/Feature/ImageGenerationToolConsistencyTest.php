<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('the tool and the manual command use the same per-assistant image-gen configuration', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureImageGenModel($user, $assistant, 'https://custom-image-gen.test/images');

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'generate_image', ['prompt' => 'a fox']))
            ->push(finalAnswerResponse('a detailed, enhanced description of a fox'))
            ->push(finalAnswerResponse('Here is your fox!'))
            ->push(finalAnswerResponse('a detailed, enhanced description of a fox, again'))
            ->push(finalAnswerResponse('Here is another fox!')),
        'custom-image-gen.test/*' => Http::response(imageGenHttpResponse('shared-image-bytes')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'show me a fox']]],
    )->assertSuccessful();

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => '/create-image a fox']]],
    )->assertSuccessful();

    Http::assertSentCount(7);
    expect(Http::recorded(fn ($request) => $request->url() === 'https://custom-image-gen.test/images'))->toHaveCount(2);
});
