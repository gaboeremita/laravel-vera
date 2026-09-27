<?php

use Illuminate\Support\Facades\File;

test('serves an existing .mjs file with the correct content type', function () {
    $path = storage_path('app/vad/test-asset.mjs');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'export default {};');
    $this->beforeApplicationDestroyed(fn () => File::delete($path));

    $response = $this->get('/vendor/vad/test-asset.mjs');

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
});

test('returns 404 for a .mjs file that does not exist', function () {
    $response = $this->get('/vendor/vad/does-not-exist.mjs');

    $response->assertNotFound();
});

test('path traversal in the filename is stripped to a basename and 404s', function () {
    $response = $this->get('/vendor/vad/%2e%2e%2f%2e%2e%2fdoes-not-exist.mjs');

    $response->assertNotFound();
});
