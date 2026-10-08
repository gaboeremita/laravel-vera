<?php

use App\Enums\VideoStatus;
use App\Jobs\PollVideoGeneration;
use App\Models\Message;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function supportedVideoSettingsResponse(array $durations = [4, 5, 8, 12], array $aspectRatios = ['16:9', '9:16', '1:1']): array
{
    return ['data' => [[
        'id' => 'test/video-model',
        'supported_durations' => $durations,
        'supported_aspect_ratios' => $aspectRatios,
    ]]];
}

function sendCreateVideo($test, $user, $assistant, $conversation, string $content, array $images = [])
{
    return $test->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => $content, ...($images === [] ? [] : ['images' => $images])]],
    );
}

beforeEach(function () {
    Bus::fake([PollVideoGeneration::class]);
    Storage::fake('public');
});

test('an empty description is refused', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Describe what video to generate after /create-video.');

    expect(Video::count())->toBe(0);
});

test('a request without a video model is refused', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    config(['ai.video_gen.model' => null]);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video a cat on a piano')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No video generation model is configured for this assistant.');

    expect(Video::count())->toBe(0);
});

test('a video request replies in character and queues a video with the model defaults', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    $model = configureVideoGenModel($user, $assistant);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(videoDescriptionResponse('A slow dolly shot of a cat walking across piano keys'))
            ->push(finalAnswerResponse('Give me a moment, the cat is warming up.')),
        'fake-video.test/api/v1/videos/models' => Http::response(supportedVideoSettingsResponse()),
    ]);

    $response = sendCreateVideo($this, $user, $assistant, $conversation, '/create-video a cat on a piano')
        ->assertSuccessful()
        ->assertJsonPath('content', 'Give me a moment, the cat is warming up.')
        ->assertJsonPath('thinking', 'A slow dolly shot of a cat walking across piano keys')
        ->assertJsonPath('video.status', 'queued')
        ->assertJsonPath('video.url', null);

    $video = Video::sole();
    expect($video->message->role)->toBe('assistant')
        ->and($video->message->content)->toBe('Give me a moment, the cat is warming up.')
        ->and($video->video_gen_model_id)->toBe($model->id)
        ->and($video->status)->toBe(VideoStatus::Queued)
        ->and($video->prompt)->toBe('A slow dolly shot of a cat walking across piano keys')
        ->and($video->duration)->toBe(5)
        ->and($video->aspect_ratio)->toBe('16:9')
        ->and($video->generate_audio)->toBeFalse()
        ->and($response->json('video.id'))->toBe($video->id);

    Bus::assertDispatched(PollVideoGeneration::class, fn (PollVideoGeneration $job) => $job->video->is($video));
});

test('length, shape and sound from the request win over the defaults, adjusted to the closest supported value', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(videoDescriptionResponse('Rain running down a window', 40, '9:21', true))
            ->push(finalAnswerResponse('Rainy mood coming up.')),
        'fake-video.test/api/v1/videos/models' => Http::response(supportedVideoSettingsResponse()),
    ]);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video a vertical 40 second clip of rain on a window with sound')
        ->assertSuccessful();

    $video = Video::sole();
    expect($video->duration)->toBe(12)
        ->and($video->aspect_ratio)->toBe('9:16')
        ->and($video->generate_audio)->toBeTrue();
});

test('requested values are kept as asked when the provider does not list supported settings', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(videoDescriptionResponse('Rain running down a window', 40))
            ->push(finalAnswerResponse('Rainy mood coming up.')),
        'fake-video.test/api/v1/videos/models' => Http::response(['data' => []]),
    ]);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video a 40 second clip of rain')->assertSuccessful();

    expect(Video::sole()->duration)->toBe(40);
});

test('a description reply that is not JSON is used whole with the defaults', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(finalAnswerResponse('A cat on a piano, filmed from above'))
            ->push(finalAnswerResponse('On it.')),
        'fake-video.test/api/v1/videos/models' => Http::response(supportedVideoSettingsResponse()),
    ]);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video a cat on a piano')->assertSuccessful();

    $video = Video::sole();
    expect($video->prompt)->toBe('A cat on a piano, filmed from above')
        ->and($video->duration)->toBe(5);
});

test('a failing description request returns the error and creates no video', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);

    Http::fake(['fake-llm.test/*' => Http::response(['error' => 'overloaded'], 500)]);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video a cat on a piano')->assertStatus(502);

    expect(Video::count())->toBe(0);
    Bus::assertNotDispatched(PollVideoGeneration::class);
});

test('the conversation shows the video on its message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(videoDescriptionResponse('A cat on a piano'))
            ->push(finalAnswerResponse('On it.')),
        'fake-video.test/api/v1/videos/models' => Http::response(supportedVideoSettingsResponse()),
    ]);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video a cat on a piano')->assertSuccessful();
    $video = Video::sole();

    $messages = $this->actingAs($user)
        ->getJson(route('conversations.show', ['assistant' => $assistant->id, 'id' => $conversation->id]))
        ->assertSuccessful()
        ->json('messages');

    $withVideo = collect($messages)->firstWhere('id', $video->message_id);
    expect($withVideo['video'])->toMatchArray(['id' => $video->id, 'status' => 'queued', 'prompt' => 'A cat on a piano'])
        ->and(collect($messages)->where('role', 'user')->first()['video'])->toBeNull();
});

test('a world conversation starts a video the same way', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [$user, $assistant] = $scenario;
    configureVideoGenModel($user, $assistant);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(videoDescriptionResponse('The pool terrace at dusk'))
            ->push(finalAnswerResponse('Filming the terrace for you.')),
        'fake-video.test/api/v1/videos/models' => Http::response(supportedVideoSettingsResponse()),
    ]);

    sendWorldMessage($this, $scenario, [], ['message' => ['content' => '/create-video the pool terrace at dusk']])->assertSuccessful();

    expect(Video::sole()->prompt)->toBe('The pool terrace at dusk');
});

test('an attached image becomes the first frame when the public address is set', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);
    config(['ai.video_gen.public_url' => 'https://tunnel.test']);

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(videoDescriptionResponse('The camera slowly pulls back'))
            ->push(finalAnswerResponse('Animating your picture.')),
        'fake-video.test/api/v1/videos/models' => Http::response(supportedVideoSettingsResponse()),
    ]);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video the camera slowly pulls back', [base64_encode("\x89PNG fake")])
        ->assertSuccessful();

    $userImage = Message::where('role', 'user')->sole()->image;
    expect(Video::sole()->first_frame_image_id)->toBe($userImage->id);
});

test('an attached image without the public address is refused', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);
    config(['ai.video_gen.public_url' => null]);

    sendCreateVideo($this, $user, $assistant, $conversation, '/create-video the camera slowly pulls back', [base64_encode("\x89PNG fake")])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Set PUBLIC_TUNNEL_URL to generate a video from an image.');

    expect(Video::count())->toBe(0);
});
