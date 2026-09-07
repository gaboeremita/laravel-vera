<?php

use App\Events\AvatarBackgroundStatusUpdated;
use App\Jobs\GenerateAvatarBackground;
use App\Services\AvatarBackground\AvatarBackgroundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('repeated requests for the active description dispatch only one job', function () {
    [, , $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a futuristic park');
    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a futuristic park');

    Queue::assertPushed(GenerateAvatarBackground::class, 1);
});

test('each differing request immediately preempts the active one instead of waiting behind it', function () {
    [, , $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a futuristic park');
    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a neon bar');
    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a penthouse');

    Queue::assertPushed(GenerateAvatarBackground::class, 3);
    expect(Cache::get(GenerateAvatarBackground::activeRequestKeyFor($conversation->id))['description'])
        ->toBe('a penthouse');
});

test('a job superseded mid-generation discards its result instead of caching it', function () {
    [, , $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a futuristic park');
    $job = Queue::pushed(GenerateAvatarBackground::class)->first();

    $service = Mockery::mock(AvatarBackgroundService::class);
    $service->shouldReceive('generate')
        ->once()
        ->andReturnUsing(function () use ($conversation) {
            // A newer request comes in while this one is still generating.
            GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a penthouse');

            return [
                'floor_url' => '/storage/park-floor.png',
                'surroundings_url' => '/storage/park-surroundings.png',
                'source_description' => 'a futuristic park',
            ];
        });

    $job->handle($service);

    expect(Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id)))
        ->toBeNull()
        ->and(Cache::get(GenerateAvatarBackground::activeRequestKeyFor($conversation->id))['description'])
        ->toBe('a penthouse');
});

test('a stale queued request cannot run after a newer request becomes active', function () {
    [, , $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a futuristic park');
    $staleJob = Queue::pushed(GenerateAvatarBackground::class)->first();

    Cache::put(GenerateAvatarBackground::activeRequestKeyFor($conversation->id), [
        'id' => 'newer-request',
        'description' => 'a penthouse',
    ], now()->addHour());

    $service = Mockery::mock(AvatarBackgroundService::class);
    $service->shouldNotReceive('generate');

    $staleJob->handle($service);

    expect(Cache::get(GenerateAvatarBackground::activeRequestKeyFor($conversation->id))['id'])
        ->toBe('newer-request');
});

test('legacy queued requests stop after one successfully generates a background', function () {
    [, , $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    $service = Mockery::mock(AvatarBackgroundService::class);
    $service->shouldReceive('generate')
        ->once()
        ->andReturn([
            'floor_url' => '/storage/park-floor.png',
            'surroundings_url' => '/storage/park-surroundings.png',
            'source_description' => 'a futuristic park',
        ]);

    $firstJob = new GenerateAvatarBackground($conversation->assistantUser, $conversation, 'a futuristic park');
    $duplicateJob = new GenerateAvatarBackground($conversation->assistantUser, $conversation, 'a futuristic park');

    $firstJob->handle($service);
    $duplicateJob->handle($service);

    expect(Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id))['source_description'])
        ->toBe('a futuristic park');
});

test('starting and finishing a generation broadcasts a status update for the conversation', function () {
    [, , $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Event::fake([AvatarBackgroundStatusUpdated::class]);
    Queue::fake();

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a futuristic park');
    $job = Queue::pushed(GenerateAvatarBackground::class)->first();

    Event::assertDispatched(
        AvatarBackgroundStatusUpdated::class,
        fn (AvatarBackgroundStatusUpdated $event) => $event->conversationId === $conversation->id
            && $event->inProgress === true,
    );

    $service = Mockery::mock(AvatarBackgroundService::class);
    $service->shouldReceive('generate')->once()->andReturn([
        'floor_url' => '/storage/park-floor.png',
        'surroundings_url' => '/storage/park-surroundings.png',
        'source_description' => 'a futuristic park',
    ]);

    $job->handle($service);

    Event::assertDispatched(
        AvatarBackgroundStatusUpdated::class,
        fn (AvatarBackgroundStatusUpdated $event) => $event->conversationId === $conversation->id
            && $event->inProgress === false
            && $event->background['source_description'] === 'a futuristic park',
    );
});

test('a broadcast failure is logged and does not block generation from starting or completing', function () {
    [, , $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Event::listen(AvatarBackgroundStatusUpdated::class, function () {
        throw new Exception('reverb is down');
    });
    Queue::fake();
    Log::spy();

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a futuristic park');
    $job = Queue::pushed(GenerateAvatarBackground::class)->first();

    expect(Cache::get(GenerateAvatarBackground::activeRequestKeyFor($conversation->id))['description'])
        ->toBe('a futuristic park');

    $service = Mockery::mock(AvatarBackgroundService::class);
    $service->shouldReceive('generate')->once()->andReturn([
        'floor_url' => '/storage/park-floor.png',
        'surroundings_url' => '/storage/park-surroundings.png',
        'source_description' => 'a futuristic park',
    ]);

    $job->handle($service);

    expect(Cache::get(GenerateAvatarBackground::cacheKeyFor($conversation->id))['source_description'])
        ->toBe('a futuristic park')
        ->and(Cache::get(GenerateAvatarBackground::activeRequestKeyFor($conversation->id)))
        ->toBeNull();

    Log::shouldHaveReceived('warning')->withArgs(function ($message) {
        return str_contains($message, 'Failed to broadcast avatar background status.');
    })->times(3);
});

test('the database retry window exceeds the background job timeout', function () {
    expect(config('queue.connections.database.retry_after'))
        ->toBeGreaterThan(GenerateAvatarBackground::TIMEOUT_SECONDS);
});
