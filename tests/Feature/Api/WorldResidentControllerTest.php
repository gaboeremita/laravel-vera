<?php

use App\Enums\AssistantKind;
use App\Enums\AssistantPortraitType;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Settings;
use App\Models\User;
use App\Models\VrmFile;
use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function residentAssistantFor(User $user, ?bool $modelSupportsTools = true, AssistantKind $kind = AssistantKind::Assistant): Assistant
{
    $assistant = Assistant::factory()->create(['portrait_type' => AssistantPortraitType::Avatar3D, 'kind' => $kind]);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);
    VrmFile::factory()->create(['vrmable_id' => $assistant->id, 'vrmable_type' => Assistant::class]);

    if ($modelSupportsTools !== null) {
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
            'supports_tools' => $modelSupportsTools,
        ]);
        Settings::create(['user_id' => $user->id, 'assistant_id' => $assistant->id, 'data' => ['ai_model_id' => $aiModel->id]]);
    }

    return $assistant;
}

it('adds and removes a resident placement without deleting the character', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);
    $payload = ['position' => ['x' => 1, 'y' => 0, 'z' => 2], 'behavior' => 'roam', 'behaviorSettings' => ['radius' => 1]];

    $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $assistant]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('assistant.id', $assistant->id)
        ->assertJsonPath('behavior', 'roam');

    $this->actingAs($user)->deleteJson(route('worlds.residents.destroy', [$world, $assistant]))->assertNoContent();

    expect(WorldResident::where('world_id', $world->id)->exists())->toBeFalse();
    expect(Assistant::find($assistant->id))->not->toBeNull();
});

it('persists a resident-specific opening message and custom prompt', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);
    $payload = [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'openingMessage' => 'Oh, hey — you found this room?',
        'customPrompt' => 'You are especially wary of strangers near the archive.',
    ];

    $response = $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $assistant]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('openingMessage', $payload['openingMessage'])
        ->assertJsonPath('customPrompt', $payload['customPrompt']);

    expect(WorldResident::where('world_id', $world->id)->where('assistant_id', $assistant->id)->first())
        ->opening_message->toBe($payload['openingMessage'])
        ->custom_prompt->toBe($payload['customPrompt']);
});

it('persists the resident facing rotation', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);
    $payload = [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'rotation' => ['x' => 0, 'y' => 1.5708, 'z' => 0],
        'behavior' => 'stationary',
    ];

    $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $assistant]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('rotation.y', 1.5708);

    expect(WorldResident::where('world_id', $world->id)->where('assistant_id', $assistant->id)->first()->rotation)
        ->toBe($payload['rotation']);
});

it('rejects a resident without a VRM asset', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create(['portrait_type' => AssistantPortraitType::Avatar3D]);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);

    $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ])->assertUnprocessable();
});

it('rejects a resident whose model cannot call tools', function (?bool $modelSupportsTools) {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user, $modelSupportsTools);

    $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ])->assertUnprocessable()->assertJsonPath('message', 'Assistants living in a world need a model that supports tool calling. Choose one in this assistant\'s settings.');

    expect(WorldResident::where('world_id', $world->id)->exists())->toBeFalse();
})->with([
    'model without tool calling' => [false],
    'no model selected' => [null],
]);

it('places an NPC on the default model', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $npc = residentAssistantFor($user, null, AssistantKind::WorldNpc);

    $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $npc]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ])->assertSuccessful();
});

it('accepts an autonomous resident', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);

    $this->actingAs($user)->putJson(route('worlds.residents.upsert', [$world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'autonomous',
    ])->assertSuccessful()->assertJsonPath('behavior', 'autonomous');
});
