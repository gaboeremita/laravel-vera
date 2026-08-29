<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('starting a conversation with a 3D avatar assistant dispatches an initial background from its opening message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    $response = $this->actingAs($user)->postJson(
        route('conversations.store', ['assistant' => $assistant->id])
    );

    $response->assertSuccessful();
    $newConversationId = $response->json('id');

    Queue::assertPushed(GenerateAvatarBackground::class, function ($job) use ($newConversationId, $assistant) {
        return $job->conversation->id === $newConversationId
            && $job->description === $assistant->opening_message;
    });
});
