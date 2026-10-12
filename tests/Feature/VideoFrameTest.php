<?php

use App\Models\Image;
use App\Services\VideoGenProviders\VideoGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['ai.video_gen.public_url' => 'https://tunnel.test/']);
});

function storedVideoFrame(): Image
{
    [, , $conversation] = setUpAgentAssistant();
    $message = $conversation->messages()->create(['role' => 'user', 'content' => 'Animate this.']);

    return Image::storeFromBase64(base64_encode("\x89PNG frame bytes"), $message, 'messages/1/1');
}

function videoFramePath(Image $image): string
{
    return str_replace('https://tunnel.test', '', app(VideoGenerationService::class)->firstFrameUrl($image));
}

test('the first frame address points at the tunnel', function () {
    $image = storedVideoFrame();

    expect(app(VideoGenerationService::class)->firstFrameUrl($image))
        ->toStartWith("https://tunnel.test/video-frames/{$image->id}?expires=");
});

test('a signed first frame address serves the image without logging in', function () {
    $image = storedVideoFrame();

    $response = $this->get(videoFramePath($image));

    $response->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->streamedContent())->toBe("\x89PNG frame bytes");
});

test('the signature holds when the request arrives on a different host', function () {
    $image = storedVideoFrame();

    $this->get('https://laravel-vera.test'.videoFramePath($image))->assertOk();
});

test('an unsigned first frame address is refused', function () {
    $image = storedVideoFrame();

    $this->get("/video-frames/{$image->id}")->assertForbidden();
});

test('a tampered signature is refused', function () {
    $image = storedVideoFrame();

    $this->get(videoFramePath($image).'0')->assertForbidden();
});

test('a signature for one image does not open another', function () {
    $signedImage = storedVideoFrame();
    $otherImage = Image::storeFromBase64(base64_encode("\x89PNG other bytes"), $signedImage->imageable, 'messages/1/1');

    $this->get(str_replace("/video-frames/{$signedImage->id}?", "/video-frames/{$otherImage->id}?", videoFramePath($signedImage)))->assertForbidden();
});

test('an expired first frame address is refused', function () {
    $image = storedVideoFrame();
    $path = videoFramePath($image);

    $this->travel(31)->minutes();

    $this->get($path)->assertForbidden();
});

test('a signed address for a missing image is not found', function () {
    $image = storedVideoFrame();
    $foreignPath = URL::temporarySignedRoute('video-frames.show', now()->plus(minutes: 30), ['image' => $image->id + 1000], absolute: false);

    $this->get($foreignPath)->assertNotFound();
});
