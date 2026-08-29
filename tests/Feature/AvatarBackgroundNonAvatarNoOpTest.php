<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('the /change-background command has no effect for a non-3D-avatar assistant', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    Queue::fake();

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => '/change-background a futuristic park']]],
    );

    $response->assertStatus(422);
    Queue::assertNotPushed(GenerateAvatarBackground::class);
});

test('the background tool is not offered to a non-3D-avatar agent-mode assistant', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('agent');
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

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

        return ! $toolNames->contains('change_avatar_background');
    });
});
