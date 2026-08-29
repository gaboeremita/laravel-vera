<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('a provider failure leaves the previous background cache entry untouched and never surfaces to the caller', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    $previous = [
        'conversation_id' => $conversation->id,
        'floor_url' => 'https://example.test/old-floor.png',
        'surroundings_url' => 'https://example.test/old-surroundings.png',
        'source_description' => 'a bar',
        'generated_at' => now()->toIso8601String(),
    ];
    Cache::put(GenerateAvatarBackground::cacheKeyFor($conversation->id), $previous, now()->addHour());

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse("FLOOR: x\nSURROUNDINGS: y")),
        'fake-image.test/*' => Http::response(['error' => 'boom'], 500),
    ]);

    Log::spy();

    $conversationUser = $conversation->assistantUser;

    GenerateAvatarBackground::dispatchFor($conversationUser, $conversation, 'a futuristic park');

    expect(Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id)))->toBe($previous);
    expect(Cache::get(GenerateAvatarBackground::progressKeyFor($conversation->id)))->toBeNull();

    Log::shouldHaveReceived('error')->withArgs(function ($message) {
        return str_contains($message, 'Failed to generate avatar background');
    })->once();
});

test('a provider failure with no previous background leaves the conversation without one, without raising an error to the user', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse("FLOOR: x\nSURROUNDINGS: y")),
        'fake-image.test/*' => Http::response(['error' => 'boom'], 500),
    ]);

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a futuristic park');

    expect(Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id)))->toBeNull();
});
