<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\PassageLink;
use App\Models\Region;
use App\Models\User;
use App\Models\World;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use App\Models\WorldUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function regionPayload(array $overrides = []): array
{
    return [
        'name' => 'Connection Node',
        'slug' => 'connection-node',
        'description' => 'A polished sci-fi room.',
        'assistantContextPrompt' => 'You are in the Connection Node.',
        'npcContextPrompt' => 'You are a Connection Node NPC.',
        'settings' => ['theme' => 'terminal'],
        'environment' => UploadedFile::fake()->create('connection-node.glb', 100, 'model/gltf-binary'),
        ...$overrides,
    ];
}

it('creates a region in a world with required context and environment fields', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();

    $this->actingAs($user)->postJson(route('worlds.regions.store', $world), regionPayload())
        ->assertCreated()
        ->assertJsonPath('name', 'Connection Node')
        ->assertJsonPath('worldId', $world->id);

    expect($world->regions()->first())->settings->toBe(['theme' => 'terminal']);
});

it('requires a theme when creating a region', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();

    $payload = regionPayload();
    unset($payload['settings']);

    $this->actingAs($user)->postJson(route('worlds.regions.store', $world), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings.theme');
});

it('shows a region with its environment, layout and links', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create();
    $other = Region::factory()->withLayout()->create(['world_id' => $region->world_id, 'name' => 'Harbor']);
    PassageLink::factory()->create(['region_id' => $region->id, 'passage_id' => 'studio-door', 'target_region_id' => $other->id, 'target_passage_id' => 'terrace-gate']);

    $this->actingAs($user)->getJson(route('worlds.regions.show', [$region->world_id, $region]))
        ->assertSuccessful()
        ->assertJsonPath('id', $region->id)
        ->assertJsonPath('layout.passages.0.id', 'studio-door')
        ->assertJsonPath('links.0.targetRegionName', 'Harbor')
        ->assertJsonPath('links.0.targetPassageName', 'Terrace gate');
});

it('does not expose a region through another world', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $foreignRegion = Region::factory()->forUser($user)->create();

    $this->actingAs($user)->getJson(route('worlds.regions.show', [$world, $foreignRegion]))->assertNotFound();
});

it('does not expose another users region', function () {
    $region = Region::factory()->create();

    $this->actingAs(User::factory()->create())->getJson(route('worlds.regions.show', [$region->world_id, $region]))->assertForbidden();
});

it('deletes a region with its environment, residents, links and session states', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create(['environment_path' => 'worlds/1/test.glb']);
    Storage::disk('public')->put($region->environment_path, 'region');
    $other = Region::factory()->withLayout()->create(['world_id' => $region->world_id]);
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);
    $resident = $region->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);
    $visitor = $other->residents()->create(['assistant_id' => Assistant::factory()->create()->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);
    app(App\Actions\LinkPassages::class)->link($region, 'studio-door', $other, 'terrace-gate');
    $worldUser = WorldUser::where('world_id', $region->world_id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create(['region_id' => $region->id]);
    WorldSessionResident::factory()->create(['world_session_id' => $session->id, 'world_resident_id' => $visitor->id, 'region_id' => $region->id]);

    $this->actingAs($user)->deleteJson(route('worlds.regions.destroy', [$region->world_id, $region]))->assertNoContent();

    expect(Region::find($region->id))->toBeNull()
        ->and($resident->fresh())->toBeNull()
        ->and($visitor->fresh())->not->toBeNull()
        ->and(PassageLink::count())->toBe(0)
        ->and(WorldSessionResident::count())->toBe(0)
        ->and($session->fresh()->region_id)->toBeNull()
        ->and($other->world->fresh()->spawn_region_id)->toBeNull()
        ->and($other->world->fresh()->spawn_passage_id)->toBeNull()
        ->and(Assistant::find($assistant->id))->not->toBeNull();
    Storage::disk('public')->assertMissing('worlds/1/test.glb');
});

it('removes links and the spawn point when a new environment drops their passages', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create(['slug' => 'connection-node']);
    $other = Region::factory()->withLayout()->create(['world_id' => $region->world_id]);
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);
    app(App\Actions\LinkPassages::class)->link($region, 'studio-door', $other, 'terrace-gate');

    $this->actingAs($user)->withHeader('Accept', 'application/json')
        ->patch(route('worlds.regions.update', [$region->world_id, $region]), regionPayload(['environment' => UploadedFile::fake()->createWithContent('empty.glb', 'not a glb file')]))
        ->assertSuccessful()
        ->assertJsonPath('removedLinks', ['studio-door']);

    expect(PassageLink::count())->toBe(0)
        ->and($region->world->fresh()->spawn_region_id)->toBeNull();
});

it('keeps links and the spawn point when the passages stay', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create(['slug' => 'connection-node']);
    $other = Region::factory()->withLayout()->create(['world_id' => $region->world_id]);
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);
    app(App\Actions\LinkPassages::class)->link($region, 'studio-door', $other, 'terrace-gate');

    $payload = regionPayload();
    unset($payload['environment']);
    $this->actingAs($user)->withHeader('Accept', 'application/json')
        ->patch(route('worlds.regions.update', [$region->world_id, $region]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('removedLinks', []);

    expect(PassageLink::count())->toBe(2)
        ->and($region->world->fresh()->spawn_passage_id)->toBe('studio-door');
});
