<?php

use App\Models\Pose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('the pose-tags instruction and pose names are added to the system prompt when poses are configured', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'dance']);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'hello']]],
    );

    Http::assertSent(function ($request) {
        $systemContent = collect($request['messages'] ?? [])->firstWhere('role', 'system')['content'] ?? '';

        return str_contains($systemContent, '[pose:')
            && str_contains($systemContent, 'spin')
            && str_contains($systemContent, 'dance');
    });
});

test('the pose-tags section is omitted for an assistant with no poses configured', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'hello']]],
    );

    Http::assertSent(function ($request) {
        $systemContent = collect($request['messages'] ?? [])->firstWhere('role', 'system')['content'] ?? '';

        return ! str_contains($systemContent, '[pose:');
    });
});

test('the pose-tags section is omitted for an image-portrait assistant even with poses configured', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'image']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'spin']);

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Just a normal reply.')),
    ]);

    $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => 'hello']]],
    );

    Http::assertSent(function ($request) {
        $systemContent = collect($request['messages'] ?? [])->firstWhere('role', 'system')['content'] ?? '';

        return ! str_contains($systemContent, '[pose:');
    });
});
