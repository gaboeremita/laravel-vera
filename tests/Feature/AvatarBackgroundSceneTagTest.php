<?php

use App\Jobs\GenerateAvatarBackground;
use App\Models\Pose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('a [scene: ...] tag in the reply is stripped and dispatches background regeneration', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);

    Queue::fake();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('[scene: a rain-soaked rooftop] The wind picks up as we step outside.')),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => "let's go up to the roof"]]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('The wind picks up as we step outside.');

    Queue::assertPushed(GenerateAvatarBackground::class, function ($job) use ($conversation) {
        return $job->conversation->is($conversation) && $job->description === 'a rain-soaked rooftop';
    });
});

test('scene and pose tags are both handled regardless of their order', function (string $taggedReply) {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $assistant->id, 'name' => 'wave']);

    Queue::fake();

    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse($taggedReply)),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]),
        ['messages' => [['role' => 'user', 'content' => "let's go up to the roof"]]],
    );

    $response->assertSuccessful();
    expect($response->json('content'))->toBe('The wind picks up as we step outside.')
        ->and($response->json('pose'))->toBe('wave');

    Queue::assertPushed(GenerateAvatarBackground::class, function ($job) use ($conversation) {
        return $job->conversation->is($conversation) && $job->description === 'a rain-soaked rooftop';
    });
})->with([
    'scene before pose' => '[scene: a rain-soaked rooftop][wave] The wind picks up as we step outside.',
    'pose before scene' => '[wave][scene: a rain-soaked rooftop] The wind picks up as we step outside.',
]);

test('the background-tags instruction is only added to the system prompt for 3D avatar assistants', function () {
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

        return str_contains($systemContent, '[scene:')
            && str_contains($systemContent, 'Control tags may appear in any order');
    });
});
