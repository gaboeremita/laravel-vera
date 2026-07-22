<?php

test('serves an existing .mjs file with the correct content type', function () {
	$response = $this->get('/vendor/vad/ort-wasm-simd-threaded.mjs');

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
