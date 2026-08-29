<?php

use App\Jobs\GenerateAvatarBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a multi-line, multi-paragraph LLM response is parsed into full floor and surroundings prompts', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    configureImageGenModel($user, $assistant, 'https://fake-image.test/generate');

    $llmResponse = <<<'TEXT'
        FLOOR:
        Polished marble tile, pale grey with faint gold veining.
        Photographed straight down, filling the frame edge to edge.

        SURROUNDINGS:
        A grand ballroom with tall arched windows and chandeliers.
        Warm evening light spills across the room.
        TEXT;

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse($llmResponse)),
        'fake-image.test/*' => Http::response(imageGenHttpResponse()),
    ]);

    GenerateAvatarBackground::dispatchFor($conversation->assistantUser, $conversation, 'a ballroom');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://fake-image.test/generate'
            && str_contains($request['prompt'] ?? '', 'marble tile')
            && str_contains($request['prompt'] ?? '', 'Photographed straight down');
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://fake-image.test/generate'
            && str_contains($request['prompt'] ?? '', 'chandeliers')
            && str_contains($request['prompt'] ?? '', 'Warm evening light');
    });
});
