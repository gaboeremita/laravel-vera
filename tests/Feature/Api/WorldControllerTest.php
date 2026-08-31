<?php

use App\Models\Assistant;
use App\Models\AssistantUser;
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
        'environment' => UploadedFile::fake()->create('connection-node.glb', 100, 'model/gltf-binary'),
    ]);

    $response->assertCreated()->assertJsonPath('name', 'Connection Node');
    expect(World::where('user_id', $user->id)->exists())->toBeTrue();
});

it('deletes the world environment without deleting resident assistants', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $world = World::factory()->for($user)->create(['environment_path' => 'worlds/1/test.glb']);
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
    $world = World::factory()->for($user)->create();

    $this->actingAs($user)->getJson(route('worlds.show', $world))
        ->assertSuccessful()
        ->assertJsonPath('id', $world->id)
        ->assertJsonPath('residents', []);
});
