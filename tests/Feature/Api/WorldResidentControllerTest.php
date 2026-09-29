<?php

use App\Enums\AssistantKind;
use App\Enums\AssistantPortraitType;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Region;
use App\Models\Settings;
use App\Models\User;
use App\Models\VrmFile;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use App\Models\WorldUser;
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
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);
    $payload = ['position' => ['x' => 1, 'y' => 0, 'z' => 2], 'behavior' => 'roam', 'behaviorSettings' => ['radius' => 1]];

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('assistant.id', $assistant->id)
        ->assertJsonPath('behavior', 'roam');

    $this->actingAs($user)->deleteJson(route('worlds.regions.residents.destroy', [$world->world_id, $world, $assistant]))->assertNoContent();

    expect(WorldResident::where('region_id', $world->id)->exists())->toBeFalse();
    expect(Assistant::find($assistant->id))->not->toBeNull();
});

it('persists a resident-specific opening message and custom prompt', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);
    $payload = [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'openingMessage' => 'Oh, hey — you found this room?',
        'customPrompt' => 'You are especially wary of strangers near the archive.',
        'publicDescription' => 'The archivist who never seems to sleep.',
    ];

    $response = $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('openingMessage', $payload['openingMessage'])
        ->assertJsonPath('customPrompt', $payload['customPrompt'])
        ->assertJsonPath('publicDescription', $payload['publicDescription']);

    expect(WorldResident::where('region_id', $world->id)->where('assistant_id', $assistant->id)->first())
        ->opening_message->toBe($payload['openingMessage'])
        ->custom_prompt->toBe($payload['customPrompt'])
        ->public_description->toBe($payload['publicDescription']);
});

it('persists the resident facing rotation', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);
    $payload = [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'rotation' => ['x' => 0, 'y' => 1.5708, 'z' => 0],
        'behavior' => 'stationary',
    ];

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('rotation.y', 1.5708);

    expect(WorldResident::where('region_id', $world->id)->where('assistant_id', $assistant->id)->first()->rotation)
        ->toBe($payload['rotation']);
});

it('rejects a resident without a VRM asset', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create(['portrait_type' => AssistantPortraitType::Avatar3D]);
    AssistantUser::factory()->create(['assistant_id' => $assistant->id, 'user_id' => $user->id]);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ])->assertUnprocessable();
});

it('rejects a resident whose model cannot call tools', function (?bool $modelSupportsTools) {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user, $modelSupportsTools);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ])->assertUnprocessable()->assertJsonPath('message', 'Assistants living in a world need a model that supports tool calling. Choose one in this assistant\'s settings.');

    expect(WorldResident::where('region_id', $world->id)->exists())->toBeFalse();
})->with([
    'model without tool calling' => [false],
    'no model selected' => [null],
]);

it('places an NPC on the default model', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $npc = residentAssistantFor($user, null, AssistantKind::WorldNpc);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $npc]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
    ])->assertSuccessful();
});

it('accepts an autonomous resident', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'autonomous',
    ])->assertSuccessful()->assertJsonPath('behavior', 'autonomous');
});

it('persists the groups and private zones a resident may enter', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);
    $payload = [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'zoneAccess' => ['tags' => [' deprecated '], 'zones' => ['mona-house']],
    ];

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('zoneAccess', ['tags' => ['deprecated'], 'zones' => ['mona-house']]);

    expect(WorldResident::where('region_id', $world->id)->firstOrFail()->zone_access)->toBe(['tags' => ['deprecated'], 'zones' => ['mona-house']]);
});

it('returns empty zone access for a resident without any', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), ['position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary'])
        ->assertSuccessful()
        ->assertJsonPath('zoneAccess', ['tags' => [], 'zones' => []]);
});

it('rejects malformed zone access', function (array $zoneAccess, string $error) {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'zoneAccess' => $zoneAccess,
    ])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'unknown key' => [['groups' => ['deprecated']], 'zoneAccess'],
    'tags not a list' => [['tags' => ['a' => 'deprecated']], 'zoneAccess.tags'],
    'empty tag' => [['tags' => ['']], 'zoneAccess.tags.0'],
    'zone id not a slug' => [['zones' => ['Mona House']], 'zoneAccess.zones.0'],
]);

it('persists a route, a home spot, the area she keeps to and her decision pace', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $npc = residentAssistantFor($user, null, AssistantKind::WorldNpc);
    $settings = [
        'homeSpot' => ['spotId' => 'toll-booth-stool', 'activityId' => 'man-the-toll-booth'],
        'route' => [['target' => 'the-heap', 'pause' => 20], ['point' => ['x' => 1, 'y' => 0, 'z' => 2]], ['target' => 'hot-swap-tacos-stool-1', 'activity' => 'eat-tacos']],
        'area' => ['fork-yard'],
        'decisionSeconds' => ['min' => 30, 'max' => 60],
    ];

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $npc]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'route',
        'behaviorSettings' => $settings,
    ])->assertSuccessful()->assertJsonPath('behavior', 'route')->assertJsonPath('behaviorSettings.area', ['fork-yard']);

    expect(WorldResident::where('region_id', $world->id)->firstOrFail()->behavior_settings)->toBe($settings);
});

it('stores whether a resident greets on arrival and rejects a non-boolean value', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);
    $url = route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]);
    $payload = ['position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary'];

    $this->actingAs($user)->putJson($url, $payload + ['behaviorSettings' => ['greetOnArrival' => true]])
        ->assertSuccessful()
        ->assertJsonPath('behaviorSettings.greetOnArrival', true);

    $this->actingAs($user)->putJson($url, $payload + ['behaviorSettings' => ['greetOnArrival' => 'yes']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('behaviorSettings.greetOnArrival');
});

it('leaves posture out of the resident placement, since the home spot decides it', function () {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $assistant]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'stationary',
        'posture' => 'sitting',
    ])->assertSuccessful()->assertJsonMissingPath('posture');

    expect(WorldResident::where('region_id', $world->id)->firstOrFail()->getAttributes())->not->toHaveKey('posture');
});

it('rejects malformed behavior settings', function (string $behavior, array $settings, string $error) {
    $user = User::factory()->create();
    $world = Region::factory()->forUser($user)->create();
    $npc = residentAssistantFor($user, null, AssistantKind::WorldNpc);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$world->world_id, $world, $npc]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => $behavior,
        'behaviorSettings' => $settings,
    ])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'unknown key' => ['stationary', ['speed' => 2], 'behaviorSettings'],
    'route resident without a route' => ['route', ['area' => ['fork-yard']], 'behaviorSettings.route'],
    'route of one stop' => ['route', ['route' => [['target' => 'the-heap']]], 'behaviorSettings.route'],
    'stop with neither target nor point' => ['route', ['route' => [['pause' => 5], ['target' => 'the-heap']]], 'behaviorSettings.route.0.target'],
    'home spot without an activity' => ['stationary', ['homeSpot' => ['spotId' => 'toll-booth-stool']], 'behaviorSettings.homeSpot.activityId'],
    'pace slower at its minimum than its maximum' => ['autonomous', ['decisionSeconds' => ['min' => 60, 'max' => 30]], 'behaviorSettings.decisionSeconds.max'],
    'pace faster than ten seconds' => ['autonomous', ['decisionSeconds' => ['min' => 2, 'max' => 30]], 'behaviorSettings.decisionSeconds.min'],
]);

it('refuses to add a resident of another region of the same world', function () {
    $user = User::factory()->create();
    $harbor = Region::factory()->forUser($user)->create(['name' => 'Harbor']);
    $penthouse = Region::factory()->create(['world_id' => $harbor->world_id]);
    $assistant = residentAssistantFor($user);
    $harbor->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$penthouse->world_id, $penthouse, $assistant]), ['position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary'])
        ->assertConflict()
        ->assertJsonPath('regionId', $harbor->id)
        ->assertJsonPath('regionName', 'Harbor');
});

it('lets the same assistant be a resident of a region in another world', function () {
    $user = User::factory()->create();
    $assistant = residentAssistantFor($user);
    Region::factory()->forUser($user)->create()->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);
    $elsewhere = Region::factory()->forUser($user)->create();

    $this->actingAs($user)->putJson(route('worlds.regions.residents.upsert', [$elsewhere->world_id, $elsewhere, $assistant]), ['position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary'])
        ->assertSuccessful()
        ->assertJsonPath('regionId', $elsewhere->id);
});

it('moves a resident to another region with the default placement, keeping existing sessions as they were', function () {
    $user = User::factory()->create();
    $harbor = Region::factory()->forUser($user)->create();
    $penthouse = Region::factory()->create(['world_id' => $harbor->world_id]);
    $assistant = residentAssistantFor($user);
    $resident = $harbor->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 4, 'y' => 0, 'z' => 2], 'rotation' => ['x' => 0, 'y' => 1.5, 'z' => 0], 'behavior' => 'roam']);
    $worldUser = WorldUser::where('world_id', $harbor->world_id)->where('user_id', $user->id)->firstOrFail();
    $untouched = WorldSession::factory()->for($worldUser)->create();
    $travelled = WorldSession::factory()->for($worldUser)->create();
    WorldSessionResident::factory()->create(['world_session_id' => $travelled->id, 'world_resident_id' => $resident->id, 'region_id' => $penthouse->id, 'position' => ['x' => 9, 'y' => 0, 'z' => 9]]);

    $this->actingAs($user)->postJson(route('worlds.regions.residents.move', [$penthouse->world_id, $penthouse, $assistant]))
        ->assertSuccessful()
        ->assertJsonPath('regionId', $penthouse->id)
        ->assertJsonPath('position', ['x' => 0, 'y' => 0, 'z' => 0])
        ->assertJsonPath('behavior', 'stationary');

    $kept = WorldSessionResident::where('world_session_id', $untouched->id)->firstOrFail();
    expect($kept->region_id)->toBe($harbor->id)
        ->and($kept->position)->toBe(['x' => 4, 'y' => 0, 'z' => 2])
        ->and($kept->rotation)->toBe(['y' => 1.5])
        ->and(WorldSessionResident::where('world_session_id', $travelled->id)->firstOrFail()->position)->toBe(['x' => 9, 'y' => 0, 'z' => 9]);
});

it('does not move an assistant who is not a resident of the world', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->create();
    $assistant = residentAssistantFor($user);

    $this->actingAs($user)->postJson(route('worlds.regions.residents.move', [$region->world_id, $region, $assistant]))->assertNotFound();
});
