<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a message starting with an unknown command is rejected with the available commands', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake();

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => '/create-background-image the library']],
    )
        ->assertStatus(422)
        ->assertJson(['message' => 'Unknown command /create-background-image. Available commands: /create-image, /change-background, /send-voice-message.']);

    Http::assertNothingSent();
    expect($conversation->messages()->count())->toBe(0);
});

test('a message without a leading command reaches the assistant', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello.'))]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'what does /create-background-image do?']],
    )->assertSuccessful();
});
