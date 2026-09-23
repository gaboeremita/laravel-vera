<?php

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Archive;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\ImageGenModel;
use App\Models\ImageGenProvider;
use App\Models\Settings;
use App\Models\User;
use App\Models\World;
use App\Models\WorldSession;
use App\Models\WorldUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function setUpAgentAssistant(string $mode = 'agent', array $assistantAttributes = []): array
{
    $user = User::factory()->create();

    $provider = AiProvider::create([
        'user_id' => $user->id,
        'name' => 'Fake Provider',
        'url' => 'https://fake-llm.test/chat/completions',
        'api_key' => 'test-key',
        'config_schema' => [],
        'format' => 'generic',
    ]);

    $aiModel = AiModel::create([
        'provider_id' => $provider->id,
        'name' => 'Fake Model',
        'endpoint' => 'fake-model',
        'config' => [],
        'supports_tools' => true,
    ]);

    $assistant = Assistant::factory()->create([
        'mode' => $mode,
        ...$assistantAttributes,
    ]);

    $assistantUser = AssistantUser::factory()->create([
        'user_id' => $user->id,
        'assistant_id' => $assistant->id,
    ]);

    Settings::create([
        'user_id' => $user->id,
        'assistant_id' => $assistant->id,
        'data' => ['ai_model_id' => $aiModel->id],
    ]);

    $conversation = Conversation::factory()->create([
        'assistant_user_id' => $assistantUser->id,
    ]);

    return [$user, $assistant, $conversation];
}

function setUpAssistantWithArchive(): array
{
    $user = User::factory()->create();
    $archive = Archive::factory()->create(['user_id' => $user->id]);
    $assistant = Assistant::factory()->create(['archive_id' => $archive->id]);

    AssistantUser::factory()->create([
        'user_id' => $user->id,
        'assistant_id' => $assistant->id,
    ]);

    return [$user, $assistant, $archive];
}

function finalAnswerResponse(string $content): array
{
    return [
        'choices' => [[
            'message' => ['content' => $content],
            'finish_reason' => 'stop',
        ]],
    ];
}

function configureImageGenModel(User $user, Assistant $assistant, string $url, ?int $timeout = null): ImageGenModel
{
    $provider = ImageGenProvider::create([
        'user_id' => $user->id,
        'name' => 'Fake Image Provider',
        'url' => $url,
        'api_key' => 'fake-image-key',
        'config_schema' => [],
        'format' => 'openrouter',
    ]);

    $imageGenModel = ImageGenModel::create([
        'provider_id' => $provider->id,
        'name' => 'Fake Image Model',
        'endpoint' => 'fake-image-model',
        'config' => $timeout ? ['timeout' => $timeout] : [],
    ]);

    $settings = Settings::where('user_id', $user->id)->where('assistant_id', $assistant->id)->first();
    $settings->update(['data' => [...$settings->data, 'image_gen_model_id' => $imageGenModel->id]]);

    return $imageGenModel;
}

function imageGenHttpResponse(string $imageData = 'fake-image-bytes'): array
{
    return [
        'data' => [[
            'b64_json' => base64_encode($imageData),
            'media_type' => 'image/png',
        ]],
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $nodes  glTF nodes; the scene's root nodes are those no other node lists as a child.
 */
function buildTestGlb(array $nodes): string
{
    $childIndexes = collect($nodes)->pluck('children')->filter()->flatten()->all();
    $rootIndexes = array_values(array_diff(array_keys($nodes), $childIndexes));

    $json = json_encode([
        'asset' => ['version' => '2.0'],
        'scene' => 0,
        'scenes' => [['nodes' => $rootIndexes]],
        'nodes' => array_values($nodes),
    ], JSON_UNESCAPED_SLASHES);
    $json = str_pad($json, (int) ceil(strlen($json) / 4) * 4, ' ');

    return pack('a4VV', 'glTF', 2, 12 + 8 + strlen($json)).pack('VV', strlen($json), 0x4E4F534A).$json;
}

function toolCallResponse(string $callId, string $toolName, array $arguments): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => $callId,
                    'type' => 'function',
                    'function' => ['name' => $toolName, 'arguments' => json_encode($arguments)],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]],
    ];
}

/**
 * @return array{0: User, 1: Assistant, 2: Conversation, 3: World, 4: \App\Models\WorldResident, 5: WorldSession}
 */
function worldStateScenario(array $worldAttributes = [], bool $fakeReply = true): array
{
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    $world = World::factory()->forUser($user)->withLayout()->create($worldAttributes);
    $resident = $world->residents()->create([
        'assistant_id' => $assistant->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ]);
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->create(['world_user_id' => $worldUser->id]);

    if ($fakeReply) {
        Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Right here.'))]);
    }

    return [$user, $assistant, $conversation, $world, $resident, $session];
}

function sendWorldMessage($test, array $scenario, array $positions, array $extra = []): Illuminate\Testing\TestResponse
{
    [$user, $assistant, $conversation, $world, , $session] = $scenario;

    return $test->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]), [
        'messages' => [['role' => 'user', 'content' => 'Where are you, and where am I?']],
        'worldId' => $world->id,
        'worldSessionId' => $session->id,
        'positions' => $positions,
        ...$extra,
    ]);
}

function sentSystemPrompt(): string
{
    $prompt = '';
    Http::assertSent(function ($request) use (&$prompt) {
        $prompt = collect($request['messages'] ?? [])->firstWhere('role', 'system')['content'] ?? '';

        return true;
    });

    return $prompt;
}

/**
 * Fakes one agent turn: each response answers the next request to the fake LLM.
 */
function fakeTurn(array ...$responses): void
{
    $sequence = Http::sequence();
    foreach ($responses as $response) {
        $sequence->push($response);
    }
    Http::fake(['fake-llm.test/*' => $sequence]);
}

/**
 * The content of the tool result she was given back, from the request that followed her tool call.
 */
function toolResultSentBack(int $requestIndex = 1): string
{
    $request = Http::recorded()[$requestIndex][0];

    return collect($request['messages'])->where('role', 'tool')->last()['content'] ?? '';
}
