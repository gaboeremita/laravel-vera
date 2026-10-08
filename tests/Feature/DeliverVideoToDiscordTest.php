<?php

use App\Events\VideoGenerationFinished;
use App\Listeners\DeliverVideoToDiscord;
use App\Models\Conversation;
use App\Models\Video;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ai.discord.api_url' => 'http://discord-api.test', 'ai.discord.api_secret' => 'secret']);
});

/**
 * @return array{0: Conversation, 1: Video}
 */
function discordConversationWithVideo(Closure $videoFactory, ?string $requestMessageId = 'discord-request-1', ?string $channelId = 'discord-channel-1'): array
{
    [, , $conversation] = setUpAgentAssistant('assistant');
    $conversation->update(['discord_channel_id' => $channelId]);

    $video = addDiscordVideoRequest($conversation, $videoFactory, $requestMessageId);

    return [$conversation, $video];
}

function addDiscordVideoRequest(Conversation $conversation, Closure $videoFactory, ?string $requestMessageId): Video
{
    $conversation->messages()->create(['role' => 'user', 'content' => '/create-video a cat', 'discord_message_id' => $requestMessageId]);
    $assistantMessage = $conversation->messages()->create(['role' => 'assistant', 'content' => 'On it.']);

    return $videoFactory()->create(['videoable_id' => $assistantMessage->id]);
}

function deliverVideoToDiscord(Video $video, ?DeliverVideoToDiscord $listener = null): void
{
    ($listener ?? app(DeliverVideoToDiscord::class))->handle(new VideoGenerationFinished($video));
}

test('a finished Discord video is sent with its address and reply target', function () {
    Http::fake(['discord-api.test/*' => Http::response(['posted' => 'video'])]);
    [$conversation, $video] = discordConversationWithVideo(fn () => Video::factory()->completed());

    deliverVideoToDiscord($video);

    $assistantId = $conversation->assistantUser()->assistant_id;
    Http::assertSent(fn (Request $request) => $request->url() === "http://discord-api.test/assistants/{$assistantId}/channels/discord-channel-1/videos"
        && $request->method() === 'POST'
        && $request->header('X-Internal-Secret') === ['secret']
        && $request->data() === ['replyToMessageId' => 'discord-request-1', 'videoUrl' => $video->fresh()->url]);
});

test('a failed Discord video is sent with its failure reason', function () {
    Http::fake(['discord-api.test/*' => Http::response(['posted' => 'notice'])]);
    [, $video] = discordConversationWithVideo(fn () => Video::factory()->failed('Provider error'));

    deliverVideoToDiscord($video);

    Http::assertSent(fn (Request $request) => $request->data() === ['replyToMessageId' => 'discord-request-1', 'failureReason' => 'Provider error']);
});

test('the reply target is the request just before the video', function () {
    Http::fake(['discord-api.test/*' => Http::response(['posted' => 'video'])]);
    [$conversation] = discordConversationWithVideo(fn () => Video::factory()->completed(), 'discord-request-1');
    $secondVideo = addDiscordVideoRequest($conversation, fn () => Video::factory()->completed(), 'discord-request-2');
    $firstVideo = Video::where('id', '!=', $secondVideo->id)->sole();

    deliverVideoToDiscord($firstVideo);
    deliverVideoToDiscord($secondVideo);

    $replyTargets = Http::recorded()->map(fn (array $pair) => $pair[0]->data()['replyToMessageId'])->all();
    expect($replyTargets)->toBe(['discord-request-1', 'discord-request-2']);
});

test('a request without a Discord message ID is sent without a reply target', function () {
    Http::fake(['discord-api.test/*' => Http::response(['posted' => 'video'])]);
    [, $video] = discordConversationWithVideo(fn () => Video::factory()->completed(), null);

    deliverVideoToDiscord($video);

    Http::assertSent(fn (Request $request) => $request->data()['replyToMessageId'] === null);
});

test('web conversations are not queued for Discord delivery', function () {
    [, $video] = discordConversationWithVideo(fn () => Video::factory()->completed(), 'discord-request-1', null);

    expect(app(DeliverVideoToDiscord::class)->shouldQueue(new VideoGenerationFinished($video)))->toBeFalse();
});

test('Discord conversations are queued for delivery', function () {
    [, $video] = discordConversationWithVideo(fn () => Video::factory()->completed());

    expect(app(DeliverVideoToDiscord::class)->shouldQueue(new VideoGenerationFinished($video)))->toBeTrue();
});

test('nothing is sent when the conversation was deleted after the video finished', function () {
    Http::fake();
    [$conversation, $video] = discordConversationWithVideo(fn () => Video::factory()->completed());
    $event = new VideoGenerationFinished($video);

    $conversation->delete();
    app(DeliverVideoToDiscord::class)->handle($event);

    Http::assertNothingSent();
});

test('delivery retries five times with growing waits', function () {
    $listener = app(DeliverVideoToDiscord::class);

    expect($listener->backoff())->toBe([10, 30, 60, 120, 300])
        ->and($listener->tries)->toBe(6)
        ->and($listener->timeout)->toBe(200);
});

test('an unavailable Discord API service makes delivery throw so it is retried', function (Closure $answer) {
    Http::fake(['discord-api.test/*' => $answer]);
    [, $video] = discordConversationWithVideo(fn () => Video::factory()->completed());

    expect(fn () => deliverVideoToDiscord($video))->toThrow(Exception::class);
})->with([
    'unreachable' => [fn () => fn () => throw new ConnectionException('down')],
    'no bot' => [fn () => fn () => Http::response(['message' => 'No bot configured'], 404)],
    'download failed' => [fn () => fn () => Http::response(['message' => 'download failed'], 502)],
    'crashed' => [fn () => fn () => Http::response('oops', 500)],
]);

test('a refused post fails delivery without a retry', function (int $status) {
    Http::fake(['discord-api.test/*' => Http::response(['message' => 'refused'], $status)]);
    [, $video] = discordConversationWithVideo(fn () => Video::factory()->completed());

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('fail')->once()->with(Mockery::on(fn (Throwable $exception) => str_contains($exception->getMessage(), (string) $status)));
    $listener = app(DeliverVideoToDiscord::class);
    $listener->setJob($job);

    deliverVideoToDiscord($video, $listener);
})->with([401, 422]);

test('a failed delivery is logged once with the video and conversation', function () {
    Log::spy();
    [$conversation, $video] = discordConversationWithVideo(fn () => Video::factory()->completed());

    app(DeliverVideoToDiscord::class)->failed(new VideoGenerationFinished($video), new RuntimeException('down'));

    Log::shouldHaveReceived('error')->once()->with('Discord video delivery failed', [
        'video_id' => $video->id,
        'conversation_id' => $conversation->id,
        'error' => 'down',
    ]);
});
