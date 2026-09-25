<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Pose;
use App\Models\PoseAnimationFile;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates a world with required context and environment fields', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('worlds.store'), [
        'name' => 'Connection Node',
        'slug' => 'connection-node',
        'description' => 'A polished sci-fi room.',
        'assistantContextPrompt' => 'You are in the Connection Node.',
        'npcContextPrompt' => 'You are a Connection Node NPC.',
        'settings' => ['theme' => 'terminal'],
        'environment' => UploadedFile::fake()->create('connection-node.glb', 100, 'model/gltf-binary'),
    ]);

    $response->assertCreated()->assertJsonPath('name', 'Connection Node');
    expect($user->worlds()->first())->settings->toBe(['theme' => 'terminal']);
});

it('requires a theme when creating a world', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.store'), [
        'name' => 'Connection Node',
        'slug' => 'connection-node',
        'description' => 'A polished sci-fi room.',
        'assistantContextPrompt' => 'You are in the Connection Node.',
        'npcContextPrompt' => 'You are a Connection Node NPC.',
        'environment' => UploadedFile::fake()->create('connection-node.glb', 100, 'model/gltf-binary'),
    ])->assertUnprocessable()->assertJsonValidationErrors('settings.theme');
});

it('deletes the world environment without deleting resident assistants', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create(['environment_path' => 'worlds/1/test.glb']);
    Storage::disk('public')->put($world->environment_path, 'world');
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);
    $world->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $this->actingAs($user)->deleteJson(route('worlds.destroy', $world))->assertNoContent();

    expect(World::find($world->id))->toBeNull();
    expect(Assistant::find($assistant->id))->not->toBeNull();
    Storage::disk('public')->assertMissing('worlds/1/test.glb');
});

it('does not expose another users world', function () {
    $world = World::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('worlds.show', $world))->assertForbidden();
});

it('returns a runtime-ready world with an empty resident list', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();

    $this->actingAs($user)->getJson(route('worlds.show', $world))
        ->assertSuccessful()
        ->assertJsonPath('id', $world->id)
        ->assertJsonPath('residents', []);
});

it('includes a resident walk pose animation for world movement', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);
    $world->residents()->create([
        'assistant_id' => $assistant->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'roam',
    ]);
    $walkPose = Pose::factory()->for($assistant)->create(['name' => 'walk']);
    $animation = PoseAnimationFile::factory()->for($walkPose)->create(['path' => 'poses/walk.vrma']);

    $this->actingAs($user)->getJson(route('worlds.show', $world))
        ->assertSuccessful()
        ->assertJsonPath('residents.0.assistant.poses.0.name', 'walk')
        ->assertJsonPath('residents.0.assistant.poses.0.animationUrl', Storage::disk('public')->url($animation->path));
});

it('tells the world which resident poses hold', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $assistant = Assistant::factory()->create();
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $assistant->id]);
    $world->residents()->create([
        'assistant_id' => $assistant->id,
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
        'behavior' => 'roam',
    ]);
    Pose::factory()->for($assistant)->create(['name' => 'wave']);
    Pose::factory()->for($assistant)->held()->create(['name' => 'sleep']);

    $this->actingAs($user)->getJson(route('worlds.show', $world))
        ->assertSuccessful()
        ->assertJsonPath('residents.0.assistant.poses.0.hold', false)
        ->assertJsonPath('residents.0.assistant.poses.1.hold', true);
});
