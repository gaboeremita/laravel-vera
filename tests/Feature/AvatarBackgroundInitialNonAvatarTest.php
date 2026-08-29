<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('starting a conversation with a non-3D-avatar assistant never dispatches an initial background', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');

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
