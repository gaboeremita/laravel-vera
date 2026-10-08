<?php

use App\Events\VideoGenerationFinished;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

test('a finished video is announced on its owner\'s channel', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'On it.']);
    $video = Video::factory()->for($message)->failed('content policy')->create();

    $event = new VideoGenerationFinished($video);

    expect($event->broadcastOn()[0]->name)->toBe("private-user.{$user->id}")
        ->and($event->broadcastAs())->toBe('video-generation.finished')
        ->and($event->broadcastWith())->toBe([
            'conversationId' => $conversation->id,
            'assistantId' => $assistant->id,
            'assistantName' => $assistant->name,
            'status' => 'failed',
            'failureReason' => 'content policy',
        ]);
});

test('only the owner may listen on a user channel', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $authorize = Broadcast::getChannels()->get('user.{userId}');

    expect($authorize($owner, $owner->id))->toBeTrue()
        ->and($authorize($other, $owner->id))->toBeFalse();
});
