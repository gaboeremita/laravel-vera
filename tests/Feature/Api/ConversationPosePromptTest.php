<?php

use App\Models\Pose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('pose names are added to the system prompt under a pose tags section when poses are configured', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'dance']);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'hello']],
    );

    expect(promptOfRequest())->toContain('# POSE TAGS')
        ->toContain('[pose: <exact pose name>]')
        ->toContain("# CURRENT STATE\nPose tags:")
        ->toContain('spin')
        ->toContain('dance');
});

test('the pose-tags section is omitted for an assistant with no poses configured', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'hello']],
    );

    expect(promptOfRequest())->not->toContain('# POSE TAGS')->not->toContain('Pose tags:');
});

test('the pose-tags section is omitted for an image-portrait assistant even with poses configured', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'image']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'hello']],
    );

    expect(promptOfRequest())->not->toContain('# POSE TAGS')->not->toContain('Pose tags:');
});

test('the emotion-tags section documents identified emotion tags', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'image']);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['message' => ['content' => 'hello']],
    );

    expect(promptOfRequest())->toContain('# EMOTION TAGS')->toContain('[emotion: <exact emotion name>]');
});
