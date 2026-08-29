<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('the /change-background command dispatches generation and replies in character for a 3D avatar assistant', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    Queue::fake();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('The scenery shifts around us as we arrive at the park.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => '/change-background a futuristic park']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('The scenery shifts around us as we arrive at the park.');

    Queue::assertPushed(GenerateAvatarBackground::class, function ($job) use ($conversation, $assistant) {
        return $job->conversation->is($conversation)
            && $job->assistantUser->assistant_id === $assistant->id
            && $job->description === 'a futuristic park';
    });
});

test('the /change-background command requires a description', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => '/change-background']]],
    );

    $response->assertStatus(422);
    Queue::assertNotPushed(GenerateAvatarBackground::class);
});
