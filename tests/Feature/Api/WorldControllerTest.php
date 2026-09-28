<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Pose;
use App\Models\PoseAnimationFile;
use App\Models\Region;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function worldPayload(array $overrides = []): array
{
    return [
        'name' => 'The Bridge',
        'slug' => 'the-bridge',
        'description' => 'Everything, connected.',
        'assistantContextPrompt' => 'You live on The Bridge.',
        'npcContextPrompt' => 'You work on The Bridge.',
        ...$overrides,
    ];
}

it('creates a world without an environment', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.store'), worldPayload())
        ->assertCreated()
        ->assertJsonPath('name', 'The Bridge')
        ->assertJsonPath('hasSpawn', false);

    expect($user->worlds()->first()->slug)->toBe('the-bridge');
});

it('lists worlds with their region count and whether they have a spawn point', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create();
    Region::factory()->create(['world_id' => $region->world_id]);
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);

    $this->actingAs($user)->getJson(route('worlds.index'))
        ->assertSuccessful()
        ->assertJsonPath('0.regionCount', 2)
        ->assertJsonPath('0.hasSpawn', true);
});

it('shows a world with its regions, passages, warnings and residents', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create(['name' => 'Lua Building']);
    $empty = Region::factory()->create(['world_id' => $region->world_id]);
    $assistant = Assistant::factory()->create();
    $region->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $this->actingAs($user)->getJson(route('worlds.show', $region->world_id))
        ->assertSuccessful()
        ->assertJsonPath('regions.0.name', 'Lua Building')
        ->assertJsonPath('regions.0.passages.0.name', 'Studio door')
        ->assertJsonPath('regions.0.warnings.unlinkedPassages', 2)
        ->assertJsonPath('regions.1.id', $empty->id)
        ->assertJsonPath('regions.1.warnings.noPassages', true)
        ->assertJsonPath('residents.0.regionId', $region->id);
});

it('sets and clears the spawn point', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create();

    $this->actingAs($user)->patchJson(route('worlds.update', $region->world_id), worldPayload(['spawnRegionId' => $region->id, 'spawnPassageId' => 'studio-door']))
        ->assertSuccessful()
        ->assertJsonPath('spawnPassageId', 'studio-door')
        ->assertJsonPath('hasSpawn', true);

    $this->actingAs($user)->patchJson(route('worlds.update', $region->world_id), worldPayload(['spawnRegionId' => null, 'spawnPassageId' => null]))
        ->assertSuccessful()
        ->assertJsonPath('hasSpawn', false);
});

it('rejects a spawn point that is not a passage of a region of the world', function (Closure $spawn) {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create();

    $this->actingAs($user)->patchJson(route('worlds.update', $region->world_id), worldPayload($spawn($region)))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('spawnPassageId');
})->with([
    'unknown passage' => [fn (Region $region) => ['spawnRegionId' => $region->id, 'spawnPassageId' => 'nowhere']],
    'region of another world' => [fn (Region $region) => ['spawnRegionId' => Region::factory()->withLayout()->create()->id, 'spawnPassageId' => 'studio-door']],
]);

it('deletes a world with its regions and their environments, keeping the assistants', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->create(['environment_path' => 'worlds/1/test.glb']);
    Storage::disk('public')->put($region->environment_path, 'region');
    $assistant = Assistant::factory()->create();
    $region->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $this->actingAs($user)->deleteJson(route('worlds.destroy', $region->world_id))->assertNoContent();

    expect(World::find($region->world_id))->toBeNull()
        ->and(Region::find($region->id))->toBeNull()
        ->and(Assistant::find($assistant->id))->not->toBeNull();
    Storage::disk('public')->assertMissing('worlds/1/test.glb');
});

it('does not expose another users world', function () {
    $world = World::factory()->create();

    $this->actingAs(User::factory()->create())->getJson(route('worlds.show', $world))->assertForbidden();
});

it('includes a resident walk pose animation for world movement', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);
    $region->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'roam']);
    $walkPose = Pose::factory()->for($assistant)->create(['name' => 'walk']);
    $animation = PoseAnimationFile::factory()->for($walkPose)->create(['path' => 'poses/walk.vrma']);

    $this->actingAs($user)->getJson(route('worlds.show', $region->world_id))
        ->assertSuccessful()
        ->assertJsonPath('residents.0.assistant.poses.0.name', 'walk')
        ->assertJsonPath('residents.0.assistant.poses.0.animationUrl', Storage::disk('public')->url($animation->path));
});

it('tells the world which resident poses hold', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);
    $region->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'roam']);
    Pose::factory()->for($assistant)->create(['name' => 'wave']);
    Pose::factory()->for($assistant)->held()->create(['name' => 'sleep']);

    $this->actingAs($user)->getJson(route('worlds.show', $region->world_id))
        ->assertSuccessful()
        ->assertJsonPath('residents.0.assistant.poses.0.hold', false)
        ->assertJsonPath('residents.0.assistant.poses.1.hold', true);
});
