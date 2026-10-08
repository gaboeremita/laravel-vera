<?php

use App\Actions\BuildConversationHistory;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('later turns see each video with its description and status', function (string $state, string $note) {
    [, $assistant, $conversation] = setUpAgentAssistant();
    $conversation->messages()->create(['role' => 'user', 'content' => 'make me a clip of the beach']);
    $carrier = $conversation->messages()->create(['role' => 'assistant', 'content' => '']);
    $factory = Video::factory()->for($carrier);
    $factory = match ($state) {
        'generating' => $factory->generating(),
        'completed' => $factory->completed(),
        'failed' => $factory->failed('content policy'),
    };
    $factory->create(['prompt' => 'A sunny beach']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'what did you think of it?']);

    $history = app(BuildConversationHistory::class)->handle($conversation, $assistant);

    expect($history)->toHaveCount(3)
        ->and($history[1])->toBe(['role' => 'assistant', 'content' => $note]);
})->with([
    'generating' => ['generating', '[Video: "A sunny beach" — generating]'],
    'ready' => ['completed', '[Video: "A sunny beach" — ready]'],
    'failed' => ['failed', '[Video: "A sunny beach" — failed: content policy]'],
]);

test('a reply that started a video keeps its text and adds the note', function () {
    [, $assistant, $conversation] = setUpAgentAssistant();
    $reply = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Filming now.']);
    Video::factory()->for($reply)->create(['prompt' => 'A cat on a piano']);

    $history = app(BuildConversationHistory::class)->handle($conversation, $assistant);

    expect($history[0]['content'])->toBe("Filming now.\n[Video: \"A cat on a piano\" — generating]");
});

test('messages without a video are unchanged and empty ones stay out', function () {
    [, $assistant, $conversation] = setUpAgentAssistant();
    $conversation->messages()->create(['role' => 'user', 'content' => 'hello']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => '']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Hi there.']);

    expect(app(BuildConversationHistory::class)->handle($conversation, $assistant))->toBe([
        ['role' => 'user', 'content' => 'hello'],
        ['role' => 'assistant', 'content' => 'Hi there.'],
    ]);
});
