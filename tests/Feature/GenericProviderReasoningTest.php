<?php

use App\Models\AiModel;
use App\Services\LlmProviders\GenericProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function reasoningModel(): AiModel
{
    return AiModel::factory()->create(['thinking_key' => 'reasoning_content'])->load('provider');
}

function reasoningReply(?string $content, ?string $reasoning): array
{
    return [
        'choices' => [['message' => ['role' => 'assistant', 'content' => $content, 'reasoning_content' => $reasoning]]],
    ];
}

test('a reply delivered only in the reasoning field becomes the content', function (?string $emptyContent) {
    Http::fake(['fake-llm.test/*' => Http::response(reasoningReply($emptyContent, '*leans on the rail* "It is."'))]);

    $response = GenericProvider::fromModel(reasoningModel())->chat([['role' => 'user', 'content' => 'Nice view.']]);

    expect($response->content)->toBe('*leans on the rail* "It is."')
        ->and($response->thinking)->toBeNull();
})->with([
    'empty string' => [''],
    'null' => [null],
]);

test('a reply with content keeps its reasoning separate', function () {
    Http::fake(['fake-llm.test/*' => Http::response(reasoningReply('"It is."', 'The user likes the view.'))]);

    $response = GenericProvider::fromModel(reasoningModel())->chat([['role' => 'user', 'content' => 'Nice view.']]);

    expect($response->content)->toBe('"It is."')
        ->and($response->thinking)->toBe('The user likes the view.');
});
