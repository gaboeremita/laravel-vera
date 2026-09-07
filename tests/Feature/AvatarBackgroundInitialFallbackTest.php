<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('a conversation with no opening message never dispatches a background from the first user message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', [
        'portrait_type' => 'avatar3d',
        'opening_message' => '',
    ]);

    Queue::fake();

    $storeResponse = $this->actingAs($user)->postJson(
        route('conversations.store', ['assistant' => $assistant->id])
    );
    $newConversationId = $storeResponse->json('id');

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $newConversationId]),
        ['messages' => [['role' => 'user', 'content' => 'hey, where are we?']]],
    );

    Queue::assertNotPushed(GenerateAvatarBackground::class);
});

test('a non-empty opening message never dispatches a background on its own', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    $storeResponse = $this->actingAs($user)->postJson(
        route('conversations.store', ['assistant' => $assistant->id])
    );
    $newConversationId = $storeResponse->json('id');

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $newConversationId]),
        ['messages' => [['role' => 'user', 'content' => 'hi there']]],
    );

    Queue::assertNotPushed(GenerateAvatarBackground::class);
});
