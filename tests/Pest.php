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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
