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
function argumentlessToolCallHistory(): array
{
    return [
        ['role' => 'user', 'content' => 'Look around.'],
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [['id' => 'call_1', 'name' => 'look_around', 'arguments' => []]]],
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"seen":"a desk"}'],
    ];
}

test('a generic provider replays empty tool arguments as a JSON object', function () {
    Http::fake(['fake-llm.test/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'A desk.']]]])]);

    GenericProvider::fromModel(AiModel::factory()->create()->load('provider'))->chat(argumentlessToolCallHistory());

    Http::assertSent(fn ($request) => $request['messages'][1]['tool_calls'][0]['function']['arguments'] === '{}');
});

test('an anthropic provider replays empty tool input as a JSON object', function () {
    Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'A desk.']]])]);

    $aiModel = AiModel::factory()->for(AiProvider::factory()->anthropic(), 'provider')->create()->load('provider');

    AnthropicProvider::fromModel($aiModel)->chat(argumentlessToolCallHistory());

    Http::assertSent(fn ($request) => str_contains($request->body(), '"input":{}'));
});
