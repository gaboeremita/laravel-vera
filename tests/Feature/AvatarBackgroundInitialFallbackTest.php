<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('a conversation with no opening message dispatches an initial background from the first user message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', [
        'portrait_type' => 'avatar3d',
        'opening_message' => '',
    ]);

    Queue::fake();

    $storeResponse = $this->actingAs($user)->postJson(
        route('conversations.store', ['assistant' => $assistant->id])
    );
    $newConversationId = $storeResponse->json('id');

    Queue::assertNotPushed(GenerateAvatarBackground::class);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $newConversationId]),
        ['messages' => [['role' => 'user', 'content' => 'hey, where are we?']]],
    );

    Queue::assertPushed(GenerateAvatarBackground::class, 1);
    Queue::assertPushed(GenerateAvatarBackground::class, function ($job) use ($newConversationId) {
        return $job->conversation->id === $newConversationId && $job->description === 'hey, where are we?';
    });

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $newConversationId]),
        ['messages' => [['role' => 'user', 'content' => 'a follow-up message']]],
    );

    Queue::assertPushed(GenerateAvatarBackground::class, 1);
});

test('a non-empty opening message dispatch is not duplicated by the first user message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    $storeResponse = $this->actingAs($user)->postJson(
        route('conversations.store', ['assistant' => $assistant->id])
    );
    $newConversationId = $storeResponse->json('id');

    Queue::assertPushed(GenerateAvatarBackground::class, 1);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $newConversationId]),
        ['messages' => [['role' => 'user', 'content' => 'hi there']]],
    );

    Queue::assertPushed(GenerateAvatarBackground::class, 1);
});
