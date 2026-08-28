<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('the image tool is not offered when image generation is not configured', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    config(['ai.image_gen.url' => null]);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'hello']]],
    );

    $response->assertSuccessful();

    Http::assertSent(function ($request) {
        $toolNames = collect($request['tools'] ?? [])->pluck('function.name');

        return $toolNames->contains('get_current_datetime')
            && $toolNames->contains('basic_calculator')
            && ! $toolNames->contains('generate_image');
    });
});

test('the image tool is offered when a global default image-gen configuration exists', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'hello']]],
    );

    $response->assertSuccessful();

    Http::assertSent(function ($request) {
        $toolNames = collect($request['tools'] ?? [])->pluck('function.name');

        return $toolNames->contains('generate_image');
    });
});
