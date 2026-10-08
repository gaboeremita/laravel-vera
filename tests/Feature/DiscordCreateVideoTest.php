<?php

use App\Enums\VideoStatus;
use App\Jobs\PollVideoGeneration;
use App\Models\Message;
use App\Models\Pose;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function sendDiscordCreateVideo($test, $user, $assistant, string $content, array $images = [])
{
    return $test->actingAs($user)->postJson(
        route('conversations.sendDiscordMessage', $assistant),
        [
            'channel_id' => 'discord-channel-1',
            'message_id' => 'discord-message-1',
            'content' => $content,
            ...($images === [] ? [] : ['images' => $images]),
        ],
    );
}

function fakeDiscordVideoStart(string $description, string $reply): void
{
    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(videoDescriptionResponse($description))
            ->push(finalAnswerResponse($reply)),
        'fake-video.test/api/v1/videos/models' => Http::response(['data' => []]),
    ]);
}

beforeEach(function () {
    Bus::fake([PollVideoGeneration::class]);
    Storage::fake('public');
});

test('a Discord video request replies in character and queues a video', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');
    configureVideoGenModel($user, $assistant);
    fakeDiscordVideoStart('A slow dolly shot of a cat walking across piano keys', 'Give me a moment, the cat is warming up.');

    sendDiscordCreateVideo($this, $user, $assistant, '/create-video a cat on a piano')
        ->assertSuccessful()
        ->assertExactJson(['content' => 'Give me a moment, the cat is warming up.']);

    $video = Video::sole();
    expect($video->status)->toBe(VideoStatus::Queued)
        ->and($video->videoable->role)->toBe('assistant')
        ->and($video->videoable->conversation->discord_channel_id)->toBe('discord-channel-1');

    Bus::assertDispatched(PollVideoGeneration::class);
});

test('the Discord request message keeps its Discord message ID', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');
    configureVideoGenModel($user, $assistant);
    fakeDiscordVideoStart('A cat on a piano', 'On it.');

    sendDiscordCreateVideo($this, $user, $assistant, '/create-video a cat on a piano')->assertSuccessful();

    expect(Message::where('role', 'user')->sole()->discord_message_id)->toBe('discord-message-1');
});

test('an empty Discord video description is refused', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');
    configureVideoGenModel($user, $assistant);

    sendDiscordCreateVideo($this, $user, $assistant, '/create-video')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Describe what video to generate after /create-video.');

    expect(Video::count())->toBe(0);
    Bus::assertNotDispatched(PollVideoGeneration::class);
});

test('a Discord video request without a video model is refused', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');
    config(['ai.video_gen.model' => null]);

    sendDiscordCreateVideo($this, $user, $assistant, '/create-video a cat on a piano')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No video generation model is configured for this assistant.');

    expect(Video::count())->toBe(0);
});

test('a failed description request answers with the error and starts nothing', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');
    configureVideoGenModel($user, $assistant);

    Http::fake([
        'fake-llm.test/*' => Http::response('upstream down', 500),
        'fake-video.test/api/v1/videos/models' => Http::response(['data' => []]),
    ]);

    sendDiscordCreateVideo($this, $user, $assistant, '/create-video a cat on a piano')
        ->assertStatus(502)
        ->assertJsonStructure(['message']);

    expect(Video::count())->toBe(0);
    Bus::assertNotDispatched(PollVideoGeneration::class);
});

test('the Discord reply to a video request leaves out expression tags', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);
    configureVideoGenModel($user, $assistant);
    fakeDiscordVideoStart('A cat on a piano', 'On it.');

    sendDiscordCreateVideo($this, $user, $assistant, '/create-video a cat on a piano')->assertSuccessful();

    expect(promptOfRequest(1))->not->toContain('# POSE TAGS')
        ->not->toContain('Pose tags:')
        ->not->toContain('# EMOTION TAGS');
});

test('an image attached to a Discord video request becomes the first frame', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');
    configureVideoGenModel($user, $assistant);
    config(['ai.video_gen.public_url' => 'https://tunnel.test']);
    fakeDiscordVideoStart('The camera slowly pulls back', 'Animating your picture.');

    sendDiscordCreateVideo($this, $user, $assistant, '/create-video the camera slowly pulls back', [base64_encode("\x89PNG fake")])
        ->assertSuccessful();

    $userImage = Message::where('role', 'user')->sole()->image;
    expect(Video::sole()->first_frame_image_id)->toBe($userImage->id);
});

test('an image attached to a Discord video request without the public address is refused', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');
    configureVideoGenModel($user, $assistant);
    config(['ai.video_gen.public_url' => null]);

    sendDiscordCreateVideo($this, $user, $assistant, '/create-video the camera slowly pulls back', [base64_encode("\x89PNG fake")])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Set PUBLIC_TUNNEL_URL to generate a video from an image.');

    expect(Video::count())->toBe(0);
    Bus::assertNotDispatched(PollVideoGeneration::class);
});
