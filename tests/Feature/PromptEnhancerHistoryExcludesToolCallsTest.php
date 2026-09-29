<?php

use App\Models\Conversation;
use App\Services\AvatarBackground\AvatarBackgroundPromptEnhancer;
use App\Services\ImageGenProviders\ImageGenPromptEnhancer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function seedHistoryWithToolCall(Conversation $conversation): void
{
    $rows = [
        ['role' => 'user', 'content' => 'show me the library'],
        ['role' => 'tool_call', 'tool_calls' => [['id' => 'call_1', 'name' => 'generate_image', 'arguments' => ['prompt' => 'the library'], 'result' => null, 'error' => 'failed']]],
        ['role' => 'assistant', 'content' => ''],
        ['role' => 'assistant', 'content' => 'Here it is.'],
        ['role' => 'user', 'content' => 'draw it again'],
    ];

    foreach ($rows as $index => $row) {
        Carbon::setTestNow(now()->startOfSecond()->addSeconds($index + 1));
        $conversation->messages()->create($row);
    }

    Carbon::setTestNow();
}

/**
 * @return array<int, array{0: string, 1: string}>
 */
function sentHistory(): array
{
    return collect(Http::recorded()->first()[0]['messages'])
        ->map(fn (array $message) => [$message['role'], $message['content']])
        ->all();
}

test('the image prompt enhancer leaves tool call rows and empty messages out of the history', function () {
    [, , $conversation] = setUpAgentAssistant();
    seedHistoryWithToolCall($conversation);

    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('a detailed library'))]);

    (new ImageGenPromptEnhancer)->enhance('the library', $conversation->assistantUser(), $conversation);

    expect(array_slice(sentHistory(), 1))->toBe([
        ['user', 'show me the library'],
        ['assistant', 'Here it is.'],
        ['user', 'the library'],
    ]);
});

test('the avatar background prompt enhancer leaves tool call rows and empty messages out of the history', function () {
    [, , $conversation] = setUpAgentAssistant('assistant', ['portrait_type' => 'avatar3d']);
    seedHistoryWithToolCall($conversation);

    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse("FLOOR: stone tiles\nSURROUNDINGS: tall shelves"))]);

    (new AvatarBackgroundPromptEnhancer)->enhance('the library', $conversation->assistantUser(), $conversation);

    expect(array_slice(sentHistory(), 1, -1))->toBe([
        ['user', 'show me the library'],
        ['assistant', 'Here it is.'],
        ['user', 'draw it again'],
    ]);
});
