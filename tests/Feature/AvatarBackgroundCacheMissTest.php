<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('reopening a conversation with no cached background regenerates automatically', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    $this->actingAs($user)->getJson(
        route('conversations.show', ['assistant' => $assistant->id, 'id' => $conversation->id])
    )->assertSuccessful();

    Queue::assertPushed(GenerateAvatarBackground::class, function ($job) use ($conversation) {
        return $job->conversation->is($conversation);
    });
});

test('reopening a conversation with an already-cached background does not regenerate', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Cache::put(GenerateAvatarBackground::cacheKeyFor($conversation->id), [
        'conversation_id' => $conversation->id,
        'floor_url' => 'https://example.test/floor.png',
        'surroundings_url' => 'https://example.test/surroundings.png',
        'source_description' => 'a bar',
        'generated_at' => now()->toIso8601String(),
    ], now()->addHour());

    Queue::fake();

    $this->actingAs($user)->getJson(
        route('conversations.show', ['assistant' => $assistant->id, 'id' => $conversation->id])
    )->assertSuccessful();

    Queue::assertNotPushed(GenerateAvatarBackground::class);
});

test('loading an older page of messages does not trigger background generation', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    $message = $conversation->messages()->create(['role' => 'user', 'content' => 'hi']);

    Queue::fake();

    $this->actingAs($user)->getJson(
        route('conversations.show', ['assistant' => $assistant->id, 'id' => $conversation->id]).'?before='.($message->id + 1)
    )->assertSuccessful();

    Queue::assertNotPushed(GenerateAvatarBackground::class);
});
