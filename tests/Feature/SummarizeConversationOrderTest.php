<?php

use App\Actions\SummarizeConversation;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function summarizeWithReply(Conversation $conversation, string $summary): void
{
    $conversation->messages()->create(['role' => 'user', 'content' => 'We walked to the pier.']);
    $last = $conversation->messages()->create(['role' => 'assistant', 'content' => 'The water was cold.']);
    $lockedAt = now()->toDateTimeString();
    $conversation->update(['memory_summarizing_at' => $lockedAt]);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse($summary))]);

    app(SummarizeConversation::class)->handle($conversation->fresh(), $last->id, $lockedAt);
}

it('adds a new summary after the existing memory and leaves that memory untouched', function () {
    [, , $conversation] = setUpAgentAssistant('assistant');
    $conversation->update(['long_term_memory' => "They met at the library.\n\n---\n\nThey argued about tea."]);

    summarizeWithReply($conversation, 'They walked to the pier.');

    expect($conversation->fresh()->long_term_memory)
        ->toBe("They met at the library.\n\n---\n\nThey argued about tea.\n\n---\n\nThey walked to the pier.");
});

it('stores the first summary on its own', function () {
    [, , $conversation] = setUpAgentAssistant('assistant');

    summarizeWithReply($conversation, 'They walked to the pier.');

    expect($conversation->fresh()->long_term_memory)->toBe('They walked to the pier.');
});
