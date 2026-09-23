<?php

use App\Http\Resources\WorldResource;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('serializes world context and environment without loading residents', function () {
    Storage::fake('public');
    $world = World::factory()->create([
        'environment_path' => 'worlds/room.glb',
        'assistant_context_prompt' => 'Assistant context',
        'npc_context_prompt' => 'NPC context',
    ]);

    $payload = (new WorldResource($world))->toArray(Request::create('/'));

    expect($payload)
        ->toMatchArray([
            'id' => $world->id,
            'assistantContextPrompt' => 'Assistant context',
            'npcContextPrompt' => 'NPC context',
            'environmentUrl' => Storage::disk('public')->url('worlds/room.glb'),
        ])
        ->and($payload['residents'])->toBeEmpty();
});

it('includes the world layout for members and an empty layout when there are no markers', function () {
    $user = User::factory()->create();
    $marked = World::factory()->forUser($user)->withLayout()->create();
    $unmarked = World::factory()->forUser($user)->create();

    $this->actingAs($user)->getJson(route('worlds.show', $marked))
        ->assertSuccessful()
        ->assertJsonPath('layout.zones.0.id', 'studio')
        ->assertJsonPath('layout.objects.0.spots.0.id', 'pool-lounger-1-seat');

    $this->actingAs($user)->getJson(route('worlds.show', $unmarked))
        ->assertSuccessful()
        ->assertJsonPath('layout', ['floors' => [], 'zones' => [], 'objects' => []]);
});

it('does not expose another users world layout', function () {
    $world = World::factory()->withLayout()->create();

    $this->actingAs(User::factory()->create())->getJson(route('worlds.show', $world))->assertForbidden();
});
