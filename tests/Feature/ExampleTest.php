<?php

test('the application returns a successful response', function () {
    $response = $this->withoutVite()->get('/');

    $response->assertSuccessful();
});
