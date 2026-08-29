<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('a reply with no [scene: ...] tag never dispatches background regeneration', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal in-character reply.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'how are you?']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Just a normal in-character reply.');

    Queue::assertNotPushed(GenerateAvatarBackground::class);
});

test('a non-3D-avatar assistant never has its reply parsed for a scene tag', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');

    Queue::fake();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('[scene: a coincidental bracket text] hello there')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'hi']]],
    );

    $response->assertSuccessful();
    Queue::assertNotPushed(GenerateAvatarBackground::class);
});
