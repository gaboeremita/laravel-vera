<?php

use App\Http\Resources\RegionResource;
use App\Models\User;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('serializes region context and environment', function () {
    Storage::fake('public');
    $world = Region::factory()->create([
        'environment_path' => 'worlds/room.glb',
        'assistant_context_prompt' => 'Assistant context',
        'npc_context_prompt' => 'NPC context',
    ]);

    $payload = (new RegionResource($world))->toArray(Request::create('/'));

    expect($payload)
        ->toMatchArray([
            'id' => $world->id,
            'assistantContextPrompt' => 'Assistant context',
            'npcContextPrompt' => 'NPC context',
            'environmentUrl' => Storage::disk('public')->url('worlds/room.glb'),
        ])
        ->and($payload['worldId'])->toBe($world->world_id);
});

it('includes the region layout for members and an empty layout when there are no markers', function () {
    $user = User::factory()->create();
    $marked = Region::factory()->forUser($user)->withLayout()->create();
    $unmarked = Region::factory()->forUser($user)->create();

    $this->actingAs($user)->getJson(route('worlds.regions.show', [$marked->world_id, $marked]))
        ->assertSuccessful()
        ->assertJsonPath('layout.zones.0.id', 'studio')
        ->assertJsonPath('layout.objects.0.spots.0.id', 'pool-lounger-1-seat');

    $this->actingAs($user)->getJson(route('worlds.regions.show', [$unmarked->world_id, $unmarked]))
        ->assertSuccessful()
        ->assertJsonPath('layout', ['floors' => [], 'zones' => [], 'objects' => [], 'passages' => []]);
});

it('does not expose another users region layout', function () {
    $world = Region::factory()->withLayout()->create();

    $this->actingAs(User::factory()->create())->getJson(route('worlds.regions.show', [$world->world_id, $world]))->assertForbidden();
});
