<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('a background change request completes the HTTP response without the job having run', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('The scenery shifts around us.')),
    ]);

    // Faking the queue means the background-generation job is recorded but
    // never actually executed — if the endpoint's response depended on that
    // job finishing, this request would have nothing to return and would
    // fail or hang. The in-character reaction is a separate, synchronous
    // LLM call the endpoint still makes on its own.
    Queue::fake();

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => '/change-background a futuristic park']]],
    );

    $response->assertSuccessful();
    Queue::assertPushed(GenerateAvatarBackground::class);
});
