<?php

use App\Http\Resources\WorldResource;
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
            'assistant_context_prompt' => 'Assistant context',
            'npc_context_prompt' => 'NPC context',
            'environment_url' => Storage::disk('public')->url('worlds/room.glb'),
        ])
        ->and($payload['residents'])->toBeEmpty();
});
