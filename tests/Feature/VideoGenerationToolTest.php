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

beforeEach(function () {
    Bus::fake([PollVideoGeneration::class]);
    Storage::fake('public');
});

function askForVideo($test, $user, $assistant, $conversation, string $content, array $images = [])
{
    return $test->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => $content, ...($images === [] ? [] : ['images' => $images])]],
    );
}

function fakeVideoToolTurn(array $toolArguments, array $description): void
{
    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'generate_video', $toolArguments))
            ->push(videoDescriptionResponse(...$description))
            ->push(finalAnswerResponse('Your clip is on its way.')),
        'fake-video.test/api/v1/videos/models' => Http::response(['data' => [[
            'id' => 'test/video-model',
            'supported_durations' => [4, 5, 8, 12],
            'supported_aspect_ratios' => ['16:9', '9:16', '1:1'],
        ]]]),
    ]);
}

test('the video tool is offered only when a video model is available', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    config(['ai.video_gen.model' => null]);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hi.'))]);

    askForVideo($this, $user, $assistant, $conversation, 'hello')->assertSuccessful();
    expect(offeredToolNames())->not->toContain('generate_video');

    configureVideoGenModel($user, $assistant);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hi.'))]);

    askForVideo($this, $user, $assistant, $conversation, 'hello again')->assertSuccessful();
    expect(collect(Http::recorded())->last()[0]['tools'])->not->toBeEmpty()
        ->and(collect(collect(Http::recorded())->last()[0]['tools'])->pluck('function.name'))->toContain('generate_video');
});

test('the video tool is not offered to an assistant outside agent mode', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    configureVideoGenModel($user, $assistant);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hi.'))]);

    askForVideo($this, $user, $assistant, $conversation, 'make me a clip')->assertSuccessful();

    expect(offeredToolNames())->not->toContain('generate_video');
});

test('calling the tool queues a video on its own message and the reply does not wait for it', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);
    fakeVideoToolTurn(['prompt' => 'us at the beach'], ['A sunny beach, waves rolling in']);

    $response = askForVideo($this, $user, $assistant, $conversation, 'make me a short clip of us at the beach')
        ->assertSuccessful()
        ->assertJsonPath('content', 'Your clip is on its way.');

    $video = Video::sole();
    expect($video->status)->toBe(VideoStatus::Queued)
        ->and($video->prompt)->toBe('A sunny beach, waves rolling in')
        ->and($video->videoable->content)->toBe('')
        ->and($video->duration)->toBe(5);

    $result = collect($response->json('tool_calls'))->firstWhere('name', 'generate_video')['result'];
    expect($result)->toMatchArray(['status' => 'queued', 'video_id' => $video->id, 'enhanced_prompt' => 'A sunny beach, waves rolling in']);
    Bus::assertDispatched(PollVideoGeneration::class);
});

test('tool arguments win over the description writer, which wins over the defaults', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);
    fakeVideoToolTurn(['prompt' => 'rain', 'duration' => 8], ['Rain on a window', 12, '9:16', true]);

    askForVideo($this, $user, $assistant, $conversation, 'an 8 second vertical clip of rain with sound')->assertSuccessful();

    $video = Video::sole();
    expect($video->duration)->toBe(8)
        ->and($video->aspect_ratio)->toBe('9:16')
        ->and($video->generate_audio)->toBeTrue();
});

test('an image attached to the request becomes the first frame', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);
    config(['ai.video_gen.public_url' => 'https://tunnel.test']);
    fakeVideoToolTurn(['prompt' => 'animate this'], ['The camera slowly pulls back']);

    askForVideo($this, $user, $assistant, $conversation, 'animate this please', [base64_encode("\x89PNG fake")])->assertSuccessful();

    expect(Video::sole()->first_frame_image_id)->toBe(Message::where('role', 'user')->sole()->image->id);
});

test('an attached image without the public address stops the tool before anything is created', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();
    configureVideoGenModel($user, $assistant);
    config(['ai.video_gen.public_url' => null]);
    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'generate_video', ['prompt' => 'animate this']))
            ->push(finalAnswerResponse('I need the tunnel address to animate your picture.')),
    ]);

    askForVideo($this, $user, $assistant, $conversation, 'animate this please', [base64_encode("\x89PNG fake")])->assertSuccessful();

    expect(Video::count())->toBe(0)
        ->and(Message::where('role', 'assistant')->where('content', '')->count())->toBe(0)
        ->and(toolResultSentBack())->toContain('Set PUBLIC_TUNNEL_URL to generate a video from an image.');
    Bus::assertNotDispatched(PollVideoGeneration::class);
});
