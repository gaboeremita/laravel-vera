<?php

use App\Enums\VideoStatus;
use App\Events\VideoGenerationFinished;
use App\Events\VideoGenerationStatusUpdated;
use App\Jobs\PollVideoGeneration;
use App\Models\Image;
use App\Models\Video;
use App\Services\VideoGenProviders\VideoGenerationService;
use App\Services\VideoGenProviders\VideoGenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([VideoGenerationStatusUpdated::class, VideoGenerationFinished::class]);
    Storage::fake('public');
});

function videoInConversation(array $attributes = []): Video
{
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    $model = configureVideoGenModel($user, $assistant);
    $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'On it.']);

    return Video::factory()->for($message)->for($model, 'model')->create($attributes);
}

function runVideoJob(Video $video): PollVideoGeneration
{
    $job = (new PollVideoGeneration($video))->withFakeQueueInteractions();
    $job->handle(app(VideoGenManager::class), app(VideoGenerationService::class));

    return $job;
}

test('the first run submits the job and checks back later', function () {
    $video = videoInConversation();
    Http::fake(['fake-video.test/api/v1/videos' => Http::response(['id' => 'job-1', 'status' => 'pending'], 202)]);

    $job = runVideoJob($video);

    expect($video->fresh()->job_id)->toBe('job-1')
        ->and($video->fresh()->status)->toBe(VideoStatus::Queued);
    $job->assertReleased(PollVideoGeneration::POLL_SECONDS);
    Http::assertSent(fn (Request $request) => $request['prompt'] === $video->prompt && $request['duration'] === 5);
});

test('a job in progress marks the video as generating and broadcasts it', function () {
    $video = videoInConversation(['job_id' => 'job-1']);
    Http::fake(['fake-video.test/api/v1/videos/job-1' => Http::response(['status' => 'in_progress'])]);

    runVideoJob($video)->assertReleased(PollVideoGeneration::POLL_SECONDS);

    expect($video->fresh()->status)->toBe(VideoStatus::Generating);
    Event::assertDispatched(VideoGenerationStatusUpdated::class, fn ($event) => $event->video['status'] === 'generating');
    Event::assertNotDispatched(VideoGenerationFinished::class);
});

test('a finished job is downloaded, stored and announced', function () {
    $video = videoInConversation(['job_id' => 'job-1', 'status' => VideoStatus::Generating]);
    Http::fake([
        'fake-video.test/api/v1/videos/job-1/content*' => Http::response('mp4-bytes'),
        'fake-video.test/api/v1/videos/job-1' => Http::response(['status' => 'completed', 'unsigned_urls' => ['https://fake-video.test/api/v1/videos/job-1/content?index=0']]),
    ]);

    runVideoJob($video)->assertNotReleased();

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Completed)
        ->and($video->mime_type)->toBe('video/mp4')
        ->and($video->size)->toBe(strlen('mp4-bytes'))
        ->and($video->url)->not->toBeNull();
    Storage::disk('public')->assertExists($video->path);
    Event::assertDispatched(VideoGenerationStatusUpdated::class, fn ($event) => $event->video['status'] === 'completed');
    Event::assertDispatched(VideoGenerationFinished::class, fn ($event) => $event->status === 'completed' && $event->conversationId === $video->message->conversation_id);
});

test('a job that ends badly fails the video with the reason', function (array $body, string $reason) {
    $video = videoInConversation(['job_id' => 'job-1', 'status' => VideoStatus::Generating]);
    Http::fake(['fake-video.test/api/v1/videos/job-1' => Http::response($body)]);

    runVideoJob($video)->assertNotReleased();

    expect($video->fresh()->status)->toBe(VideoStatus::Failed)
        ->and($video->fresh()->failure_reason)->toBe($reason);
    Event::assertDispatched(VideoGenerationFinished::class, fn ($event) => $event->status === 'failed' && $event->failureReason === $reason);
})->with([
    'failed' => [['status' => 'failed', 'error' => 'content policy'], 'content policy'],
    'expired' => [['status' => 'expired'], 'The video generation job ended as expired.'],
    'cancelled' => [['status' => 'cancelled'], 'The video generation job ended as cancelled.'],
]);

test('a rejected submission fails the video with the provider response', function () {
    $video = videoInConversation();
    Http::fake(['fake-video.test/api/v1/videos' => Http::response(['error' => ['message' => 'invalid duration']], 400)]);

    runVideoJob($video)->assertNotReleased();

    expect($video->fresh()->status)->toBe(VideoStatus::Failed)
        ->and($video->fresh()->failure_reason)->toContain('invalid duration');
    Event::assertDispatched(VideoGenerationFinished::class);
});

test('a failed download fails the video', function () {
    $video = videoInConversation(['job_id' => 'job-1', 'status' => VideoStatus::Generating]);
    Http::fake([
        'fake-video.test/api/v1/videos/job-1/content*' => Http::response('', 500),
        'fake-video.test/api/v1/videos/job-1' => Http::response(['status' => 'completed', 'unsigned_urls' => ['https://fake-video.test/api/v1/videos/job-1/content?index=0']]),
    ]);

    runVideoJob($video);

    expect($video->fresh()->status)->toBe(VideoStatus::Failed)
        ->and($video->fresh()->failure_reason)->toContain('Video download failed');
});

test('passing the maximum wait fails the video with a timeout', function () {
    $video = videoInConversation(['job_id' => 'job-1', 'status' => VideoStatus::Generating]);

    (new PollVideoGeneration($video))->failed(new MaxAttemptsExceededException('too long'));

    expect($video->fresh()->status)->toBe(VideoStatus::Failed)
        ->and($video->fresh()->failure_reason)->toBe('Video generation timed out after 600 seconds.');
    Event::assertDispatched(VideoGenerationFinished::class);
});

test('the deadline is the video creation time plus the model timeout', function () {
    $video = videoInConversation();

    expect((new PollVideoGeneration($video))->retryUntil()->getTimestamp())
        ->toBe($video->created_at->copy()->addSeconds(600)->getTimestamp());
});

test('a deleted conversation ends the job quietly', function () {
    $video = videoInConversation(['job_id' => 'job-1']);
    $job = new PollVideoGeneration($video);
    $serialized = serialize($job);

    $video->message->conversation->delete();

    expect(Video::count())->toBe(0)
        ->and($job->deleteWhenMissingModels)->toBeTrue();
    expect(fn () => unserialize($serialized))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    Event::assertNotDispatched(VideoGenerationFinished::class);
});

test('two videos in one conversation are tracked separately', function () {
    $first = videoInConversation(['job_id' => 'job-1', 'status' => VideoStatus::Generating]);
    $second = Video::factory()->for($first->message)->for($first->model, 'model')->create(['job_id' => 'job-2', 'status' => VideoStatus::Generating]);
    Http::fake([
        'fake-video.test/api/v1/videos/job-1' => Http::response(['status' => 'failed', 'error' => 'content policy']),
        'fake-video.test/api/v1/videos/job-2' => Http::response(['status' => 'in_progress']),
    ]);

    runVideoJob($first);
    runVideoJob($second);

    expect($first->fresh()->status)->toBe(VideoStatus::Failed)
        ->and($second->fresh()->status)->toBe(VideoStatus::Generating);
    Event::assertDispatchedTimes(VideoGenerationFinished::class, 1);
});

test('an attached image is sent as the first frame through the public address', function () {
    config(['ai.video_gen.public_url' => 'https://tunnel.test/']);
    $video = videoInConversation();
    $image = Image::storeFromBase64(base64_encode("\x89PNG fake"), $video->message, 'messages/1/1');
    $video->update(['first_frame_image_id' => $image->id]);
    Http::fake(['fake-video.test/api/v1/videos' => Http::response(['id' => 'job-1'], 202)]);

    runVideoJob($video->fresh());

    Http::assertSent(fn (Request $request) => $request['frame_images'][0]['image_url']['url'] === "https://tunnel.test/storage/{$image->path}"
        && $request['frame_images'][0]['frame_type'] === 'first_frame');
});

test('a provider rejecting the image fails the video with its reason', function () {
    config(['ai.video_gen.public_url' => 'https://tunnel.test']);
    $video = videoInConversation();
    $image = Image::storeFromBase64(base64_encode("\x89PNG fake"), $video->message, 'messages/1/1');
    $video->update(['first_frame_image_id' => $image->id]);
    Http::fake(['fake-video.test/api/v1/videos' => Http::response(['error' => ['message' => 'could not fetch image']], 400)]);

    runVideoJob($video->fresh());

    expect($video->fresh()->status)->toBe(VideoStatus::Failed)
        ->and($video->fresh()->failure_reason)->toContain('could not fetch image');
});
