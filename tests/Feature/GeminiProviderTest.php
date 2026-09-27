<?php

use App\Contracts\AgentTool;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use App\Services\AgentLoop\AgentLoopRunner;
use App\Services\LlmProviders\GeminiProvider;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta';
const GEMINI_ENDPOINT = GEMINI_URL.'/models/gemini-3-flash-preview:generateContent';

function makeGeminiProvider(array $config = []): GeminiProvider
{
    $aiProvider = AiProvider::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Gemini',
        'url' => GEMINI_URL.'/',
        'api_key' => 'gemini-key',
        'config_schema' => [],
        'format' => 'gemini',
    ]);

    $aiModel = AiModel::create([
        'provider_id' => $aiProvider->id,
        'name' => 'Gemini 3 Flash',
        'endpoint' => 'gemini-3-flash-preview',
        'config' => [],
        'additional_config' => $config,
    ]);

    return (new LlmManager)->fromModel($aiModel->load('provider'));
}

/**
 * @param  array<int, array<string, mixed>>  $parts
 */
function geminiResponse(array $parts, string $finishReason = 'STOP'): array
{
    return [
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => $parts],
            'finishReason' => $finishReason,
        ]],
    ];
}

test('the gemini format resolves to the gemini provider', function () {
    expect(makeGeminiProvider())->toBeInstanceOf(GeminiProvider::class);
});

test('it sends system instructions, roles, images and generation config in the gemini wire format', function () {
    Http::fake([GEMINI_ENDPOINT => Http::response(geminiResponse([['text' => 'Hello!']]))]);

    $response = makeGeminiProvider(['temperature' => 0.4])->chat(
        messages: [
            ['role' => 'system', 'content' => 'You are Vera.'],
            ['role' => 'system', 'content' => 'Be brief.'],
            ['role' => 'user', 'content' => 'Look at this', 'images' => ['base64-image']],
            ['role' => 'assistant', 'content' => 'Nice picture.'],
            ['role' => 'user', 'content' => 'Say hi'],
        ],
        options: ['max_tokens' => 256, 'unsupported_option' => true],
    );

    expect($response->content)->toBe('Hello!')
        ->and($response->thinking)->toBeNull()
        ->and($response->isFinal())->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === GEMINI_ENDPOINT
        && $request->header('x-goog-api-key') === ['gemini-key']
        && $request['systemInstruction'] === ['parts' => [['text' => "You are Vera.\n\nBe brief."]]]
        && $request['generationConfig'] === ['temperature' => 0.4, 'maxOutputTokens' => 256]
        && $request['contents'] === [
            ['role' => 'user', 'parts' => [
                ['text' => 'Look at this'],
                ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => 'base64-image']],
            ]],
            ['role' => 'model', 'parts' => [['text' => 'Nice picture.']]],
            ['role' => 'user', 'parts' => [['text' => 'Say hi']]],
        ]);
});

test('it separates thought parts from the answer', function () {
    Http::fake([GEMINI_ENDPOINT => Http::response(geminiResponse([
        ['text' => 'Considering the greeting.', 'thought' => true],
        ['text' => 'Hi '],
        ['text' => 'there!'],
    ]))]);

    $response = makeGeminiProvider()->chat([['role' => 'user', 'content' => 'Hi']]);

    expect($response->content)->toBe('Hi there!')
        ->and($response->thinking)->toBe('Considering the greeting.');
});

test('it declares tools and returns function calls with their thought signatures', function () {
    Http::fake([GEMINI_ENDPOINT => Http::response(geminiResponse([
        ['functionCall' => ['id' => 'call_1', 'name' => 'get_weather', 'args' => ['city' => 'Paris']], 'thoughtSignature' => 'sig-1'],
        ['functionCall' => ['name' => 'get_time', 'args' => []]],
    ]))]);

    $response = makeGeminiProvider()->chat(
        messages: [['role' => 'user', 'content' => 'Weather and time in Paris?']],
        tools: [['name' => 'get_weather', 'description' => 'Weather lookup', 'parameters' => ['type' => 'object']]],
    );

    expect($response->isFinal())->toBeFalse()
        ->and($response->toolCalls)->toHaveCount(2)
        ->and($response->toolCalls[0]->id)->toBe('call_1')
        ->and($response->toolCalls[0]->arguments)->toBe(['city' => 'Paris'])
        ->and($response->toolCalls[0]->thoughtSignature)->toBe('sig-1')
        ->and($response->toolCalls[1]->id)->not->toBeEmpty()
        ->and($response->toolCalls[1]->thoughtSignature)->toBeNull();

    Http::assertSent(fn (Request $request) => $request['tools'] === [[
        'functionDeclarations' => [[
            'name' => 'get_weather',
            'description' => 'Weather lookup',
            'parametersJsonSchema' => ['type' => 'object'],
        ]],
    ]]);
});

test('it replays tool calls with signatures and groups parallel results into one turn', function () {
    Http::fake([GEMINI_ENDPOINT => Http::response(geminiResponse([['text' => 'Sunny, 10am.']]))]);

    makeGeminiProvider()->chat([
        ['role' => 'user', 'content' => 'Weather and time?'],
        ['role' => 'assistant', 'content' => null, 'tool_calls' => [
            ['id' => 'call_1', 'name' => 'get_weather', 'arguments' => ['city' => 'Paris'], 'thoughtSignature' => 'sig-1'],
            ['id' => 'call_2', 'name' => 'get_time', 'arguments' => [], 'thoughtSignature' => null],
        ]],
        ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => json_encode(['forecast' => 'sunny'])],
        ['role' => 'tool', 'tool_call_id' => 'call_2', 'content' => json_encode(['error' => 'Clock unavailable'])],
    ]);

    Http::assertSent(function (Request $request) {
        $body = json_decode($request->body(), true);

        return count($body['contents']) === 3
            && $body['contents'][1] === ['role' => 'model', 'parts' => [
                ['functionCall' => ['id' => 'call_1', 'name' => 'get_weather', 'args' => ['city' => 'Paris']], 'thoughtSignature' => 'sig-1'],
                ['functionCall' => ['id' => 'call_2', 'name' => 'get_time', 'args' => []]],
            ]]
            && str_contains($request->body(), '"args":{}')
            && $body['contents'][2] === ['role' => 'user', 'parts' => [
                ['functionResponse' => ['id' => 'call_1', 'name' => 'get_weather', 'response' => ['output' => ['forecast' => 'sunny']]]],
                ['functionResponse' => ['id' => 'call_2', 'name' => 'get_time', 'response' => ['error' => 'Clock unavailable']]],
            ]];
    });
});

test('the agent loop sends a function call thought signature back on the next turn', function () {
    [, $assistant, $conversation] = setUpAgentAssistant();

    Http::fake([GEMINI_ENDPOINT => Http::sequence()
        ->push(geminiResponse([
            ['functionCall' => ['id' => 'call_1', 'name' => 'echo_tool', 'args' => ['text' => 'ping']], 'thoughtSignature' => 'sig-1'],
        ]))
        ->push(geminiResponse([['text' => 'Echoed ping.']])),
    ]);

    $echoTool = new class implements AgentTool
    {
        public function name(): string
        {
            return 'echo_tool';
        }

        public function description(): string
        {
            return 'Echoes the given text.';
        }

        public function parameters(): array
        {
            return ['type' => 'object', 'properties' => ['text' => ['type' => 'string']]];
        }

        public function handle(array $arguments): array
        {
            return ['echo' => $arguments['text']];
        }

        public function timeoutSeconds(): int
        {
            return 5;
        }

        public function retryAttempts(): int
        {
            return 1;
        }
    };

    $result = (new AgentLoopRunner(makeGeminiProvider(), [$echoTool]))->run(
        assistant: $assistant,
        messages: [['role' => 'user', 'content' => 'Echo ping.']],
        conversation: $conversation,
    );

    expect($result->content)->toBe('Echoed ping.');

    $followUp = Http::recorded()[1][0];

    expect($followUp['contents'][1]['parts'][0]['thoughtSignature'])->toBe('sig-1')
        ->and($followUp['contents'][2]['parts'][0]['functionResponse']['response'])->toBe(['output' => ['echo' => 'ping']]);
});

test('it throws when the request fails', function () {
    Http::fake([GEMINI_ENDPOINT => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

    makeGeminiProvider()->chat([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class, 'API key not valid');

test('it throws when the prompt is blocked', function () {
    Http::fake([GEMINI_ENDPOINT => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);

    makeGeminiProvider()->chat([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class, 'Gemini blocked the prompt (SAFETY)');

test('it throws when the candidate stops without content', function () {
    Http::fake([GEMINI_ENDPOINT => Http::response(geminiResponse([], 'SAFETY'))]);

    makeGeminiProvider()->chat([['role' => 'user', 'content' => 'Hi']]);
})->throws(RuntimeException::class, 'finish reason: SAFETY');
