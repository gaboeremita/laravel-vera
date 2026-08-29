<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('an agent-mode 3D avatar assistant asked to change the background invokes the tool', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('agent', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    Queue::fake();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'change_avatar_background', ['description' => 'a futuristic park']))
            ->push(finalAnswerResponse('Alright, changing the scene!')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'change the background to a futuristic park']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Alright, changing the scene!');
    expect($response->json('tool_calls.0.name'))->toBe('change_avatar_background');
    expect($response->json('tool_calls.0.result.status'))->toBe('queued');

    Queue::assertPushed(GenerateAvatarBackground::class, function ($job) use ($conversation) {
        return $job->conversation->is($conversation) && $job->description === 'a futuristic park';
    });
});
