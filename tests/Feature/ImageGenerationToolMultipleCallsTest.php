<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('two generate_image calls in one task each produce their own independent image message', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([
        'fake-llm.test/*' => Http::sequence()
            ->push(toolCallResponse('call_1', 'generate_image', ['prompt' => 'a cat']))
            ->push(finalAnswerResponse('a detailed, enhanced description of a cat'))
            ->push(toolCallResponse('call_2', 'generate_image', ['prompt' => 'a dog']))
            ->push(finalAnswerResponse('a detailed, enhanced description of a dog'))
            ->push(finalAnswerResponse('Here is your cat and dog!')),
        'openrouter.ai/*' => Http::sequence()
            ->push(imageGenHttpResponse('cat-bytes'))
            ->push(imageGenHttpResponse('dog-bytes')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'show me a cat, then a dog']]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('Here is your cat and dog!');

    $imageMessages = $conversation->messages()->where('role', 'assistant')->whereHas('image')->get();
    expect($imageMessages)->toHaveCount(2);
    expect($imageMessages->pluck('id')->unique())->toHaveCount(2);
    expect($imageMessages->pluck('image.id')->unique())->toHaveCount(2);
});
