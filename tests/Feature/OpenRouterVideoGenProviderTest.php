<?php

use App\Enums\VideoStatus;
use App\Models\VideoGenModel;
use App\Services\VideoGenProviders\OpenRouterVideoGenProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function openRouterVideoProvider(array $additionalConfig = []): OpenRouterVideoGenProvider
{
    $model = VideoGenModel::factory()->create(['additional_config' => $additionalConfig ?: null]);

    return OpenRouterVideoGenProvider::fromModel($model->load('provider'));
}

test('submit posts the prompt, settings and first frame and returns the job id', function () {
    Http::fake(['fake-video.test/api/v1/videos' => Http::response(['id' => 'job-1', 'status' => 'pending'], 202)]);

    $jobId = openRouterVideoProvider(['seed' => 7])->submit(
        'A cat on a piano',
        ['duration' => 5, 'aspect_ratio' => '16:9', 'generate_audio' => false, 'resolution' => null],
        'https://tunnel.test/storage/messages/1/1/frame.png',
    );

    expect($jobId)->toBe('job-1');
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'test/video-model'
        && $request['prompt'] === 'A cat on a piano'
        && $request['duration'] === 5
        && $request['aspect_ratio'] === '16:9'
        && $request['generate_audio'] === false
        && $request['seed'] === 7
        && ! array_key_exists('resolution', $request->data())
        && $request['frame_images'] === [[
            'type' => 'image_url',
            'image_url' => ['url' => 'https://tunnel.test/storage/messages/1/1/frame.png'],
            'frame_type' => 'first_frame',
        ]]);
});

test('submit leaves out frame images when there is no first frame', function () {
    Http::fake(['fake-video.test/api/v1/videos' => Http::response(['id' => 'job-1'], 202)]);

    openRouterVideoProvider()->submit('A cat on a piano');

    Http::assertSent(fn (Request $request) => ! array_key_exists('frame_images', $request->data()));
});

test('submit throws with the response body when the provider rejects the request', function () {
    Http::fake(['fake-video.test/api/v1/videos' => Http::response(['error' => ['message' => 'image unreachable']], 400)]);

    openRouterVideoProvider()->submit('A cat on a piano');
})->throws(RuntimeException::class, 'image unreachable');

test('status maps every provider state', function (array $body, VideoStatus $status, ?string $contentUrl, ?string $error) {
    Http::fake(['fake-video.test/api/v1/videos/job-1' => Http::response($body)]);

    $result = openRouterVideoProvider()->status('job-1');

    expect($result->status)->toBe($status)
        ->and($result->contentUrl)->toBe($contentUrl)
        ->and($result->error)->toBe($error);
})->with([
    'pending' => [['status' => 'pending'], VideoStatus::Queued, null, null],
    'in progress' => [['status' => 'in_progress'], VideoStatus::Generating, null, null],
    'completed' => [['status' => 'completed', 'unsigned_urls' => ['https://fake-video.test/api/v1/videos/job-1/content?index=0']], VideoStatus::Completed, 'https://fake-video.test/api/v1/videos/job-1/content?index=0', null],
    'failed with an error' => [['status' => 'failed', 'error' => 'content policy'], VideoStatus::Failed, null, 'content policy'],
    'expired' => [['status' => 'expired'], VideoStatus::Failed, null, 'The video generation job ended as expired.'],
    'cancelled' => [['status' => 'cancelled'], VideoStatus::Failed, null, 'The video generation job ended as cancelled.'],
]);

test('download writes the file with the api key', function () {
    Http::fake(['fake-video.test/api/v1/videos/job-1/content*' => Http::response('mp4-bytes')]);
    $target = tempnam(sys_get_temp_dir(), 'video');

    openRouterVideoProvider()->download('https://fake-video.test/api/v1/videos/job-1/content?index=0', $target);

    expect(file_get_contents($target))->toBe('mp4-bytes');
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key'));
    unlink($target);
});

test('download throws when the provider refuses', function () {
    Http::fake(['fake-video.test/api/v1/videos/job-1/content*' => Http::response('', 404)]);

    openRouterVideoProvider()->download('https://fake-video.test/api/v1/videos/job-1/content?index=0', tempnam(sys_get_temp_dir(), 'video'));
})->throws(RuntimeException::class);

test('supported settings come from the model listing and are cached', function () {
    Cache::flush();
    Http::fake(['fake-video.test/api/v1/videos/models' => Http::response(['data' => [
        ['id' => 'other/model', 'supported_durations' => [1], 'supported_aspect_ratios' => ['1:1']],
        ['id' => 'test/video-model', 'supported_durations' => [4, 8, 12], 'supported_aspect_ratios' => ['16:9', '9:16']],
    ]])]);
    $provider = openRouterVideoProvider();

    $settings = $provider->supportedSettings();
    $provider->supportedSettings();

    expect($settings->durations)->toBe([4, 8, 12])
        ->and($settings->aspectRatios)->toBe(['16:9', '9:16']);
    Http::assertSentCount(1);
});

test('supported settings are null when the listing does not include the model', function () {
    Cache::flush();
    Http::fake(['fake-video.test/api/v1/videos/models' => Http::response(['data' => []])]);

    expect(openRouterVideoProvider()->supportedSettings())->toBeNull();
});
