<?php

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\LlmProviders\AnthropicProvider;
use App\Services\LlmProviders\GenericProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return list<array<string, mixed>>
 */
function cacheMarkedMessages(): array
{
    return [
        ['role' => 'system', 'content' => [
            ['type' => 'text', 'text' => '# IDENTITY'."\n".'You are Vera.', 'cachePoint' => true],
            ['type' => 'text', 'text' => '# LONG-TERM MEMORY'."\n".'They met.', 'cachePoint' => true],
        ]],
        ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hi there.', 'cachePoint' => true]]],
        ['role' => 'assistant', 'content' => 'Hello!'],
        ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => '# CURRENT STATE'."\n".'You are in the library.'],
            ['type' => 'text', 'text' => 'How are you?'],
        ]],
    ];
}

function genericModel(array $attributes = []): AiModel
{
    return AiModel::factory()->create($attributes)->load('provider');
}

function anthropicModel(array $attributes = []): AiModel
{
    return AiModel::factory()->for(AiProvider::factory()->anthropic(), 'provider')->create($attributes)->load('provider');
}

function anthropicReply(): array
{
    return [
        'content' => [['type' => 'text', 'text' => 'Fine, thanks.']],
        'usage' => ['input_tokens' => 12, 'cache_read_input_tokens' => 4000, 'cache_creation_input_tokens' => 0],
    ];
}

test('a generic model without cache marks receives each parts list as one joined string', function () {
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Fine, thanks.'))]);

    GenericProvider::fromModel(genericModel())->chat(cacheMarkedMessages());

    Http::assertSent(function ($request) {
        $messages = $request['messages'];

        return $messages[0]['content'] === "# IDENTITY\nYou are Vera.\n\n# LONG-TERM MEMORY\nThey met."
            && $messages[1]['content'] === 'Hi there.'
            && $messages[3]['content'] === "# CURRENT STATE\nYou are in the library.\n\nHow are you?"
            && ! str_contains(json_encode($request->data()), 'cache_control');
    });
});

test('a generic model with cache marks receives content blocks marked only at the cache points', function () {
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Fine, thanks.'))]);

    GenericProvider::fromModel(genericModel(['cache_marks' => true]))->chat(cacheMarkedMessages());

    Http::assertSent(function ($request) {
        $messages = $request['messages'];
        $marked = collect($messages)->flatMap(fn (array $message) => is_array($message['content']) ? $message['content'] : [])
            ->filter(fn (array $block) => isset($block['cache_control']));

        return $marked->count() === 3
            && $messages[0]['content'][0]['cache_control'] === ['type' => 'ephemeral']
            && $messages[1]['content'][0]['cache_control'] === ['type' => 'ephemeral']
            && ! isset($messages[3]['content'][0]['cache_control']);
    });
});

test('the conversation key is sent under the configured field only', function () {
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Fine, thanks.'))]);

    GenericProvider::fromModel(genericModel(['conversation_key_field' => 'session_id']))->chat(cacheMarkedMessages(), conversationKey: 'abc123');
    GenericProvider::fromModel(genericModel())->chat(cacheMarkedMessages(), conversationKey: 'abc123');

    $recorded = Http::recorded();
    expect($recorded[0][0]['session_id'])->toBe('abc123')
        ->and(json_encode($recorded[1][0]->data()))->not->toContain('abc123');
});

test('an anthropic model with cache marks receives system blocks and message blocks marked at the cache points', function () {
    Http::fake(['fake-anthropic.test/*' => Http::response(anthropicReply())]);

    $response = AnthropicProvider::fromModel(anthropicModel(['cache_marks' => true, 'conversation_key_field' => 'metadata_key']))
        ->chat(cacheMarkedMessages(), conversationKey: 'abc123');

    Http::assertSent(function ($request) {
        return $request['system'][0]['cache_control'] === ['type' => 'ephemeral']
            && $request['system'][1]['cache_control'] === ['type' => 'ephemeral']
            && $request['messages'][0]['content'][0]['cache_control'] === ['type' => 'ephemeral']
            && ! isset($request['messages'][2]['content'][0]['cache_control'])
            && $request['metadata_key'] === 'abc123';
    });
    expect($response->usage['cache_read_input_tokens'])->toBe(4000);
});

test('an anthropic model without cache marks receives the joined system text', function () {
    Http::fake(['fake-anthropic.test/*' => Http::response(anthropicReply())]);

    AnthropicProvider::fromModel(anthropicModel())->chat(cacheMarkedMessages());

    Http::assertSent(fn ($request) => $request['system'] === "# IDENTITY\nYou are Vera.\n\n# LONG-TERM MEMORY\nThey met."
        && $request['messages'][2]['content'] === "# CURRENT STATE\nYou are in the library.\n\nHow are you?");
});

test('images on a parts message still go out in each provider\'s image format', function () {
    Http::fake([
        'fake-llm.test/*' => Http::response(finalAnswerResponse('Nice photo.')),
        'fake-anthropic.test/*' => Http::response(anthropicReply()),
    ]);
    $messages = [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Look.']], 'images' => ['aW1hZ2U=']]];

    GenericProvider::fromModel(genericModel())->chat($messages);
    AnthropicProvider::fromModel(anthropicModel())->chat($messages);

    $recorded = Http::recorded();
    expect($recorded[0][0]['messages'][0]['content'][1]['type'])->toBe('image_url')
        ->and($recorded[1][0]['messages'][0]['content'][1]['type'])->toBe('image');
});
