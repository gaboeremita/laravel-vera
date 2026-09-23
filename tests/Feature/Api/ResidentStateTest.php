<?php

use App\Models\User;
use App\Models\World;
use App\Models\WorldSessionResident;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a resident state and returns it with the sessions', function () {
    [$user, , , $world, $resident, $session] = worldStateScenario();
    $state = [
        'position' => ['x' => 5, 'y' => 0.2, 'z' => -5],
        'rotation' => ['y' => 1.5],
        'spotId' => 'pool-lounger-1-seat',
        'activityId' => 'recline',
        'posture' => 'reclining',
        'exitPosition' => ['x' => 5, 'y' => 0, 'z' => -4.2],
    ];

    $this->actingAs($user)->putJson(route('worlds.sessions.residents.state.update', [$world->id, $session->id, $resident->id]), $state)->assertNoContent();
    $this->actingAs($user)->putJson(route('worlds.sessions.residents.state.update', [$world->id, $session->id, $resident->id]), $state)->assertNoContent();

    expect(WorldSessionResident::where('world_resident_id', $resident->id)->count())->toBe(1);

    $this->actingAs($user)->getJson(route('worlds.sessions.index', $world->id))
        ->assertSuccessful()
        ->assertJsonPath("0.residentStates.{$resident->id}", $state);
});

it('keeps other users out of a session state', function () {
    [, , , $world, $resident, $session] = worldStateScenario();
    $stranger = User::factory()->create();
    $world->users()->attach($stranger);

    $this->actingAs($stranger)->putJson(route('worlds.sessions.residents.state.update', [$world->id, $session->id, $resident->id]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
    ])->assertNotFound();
});

it('rejects a resident from another world', function () {
    [$user, , , $world, , $session] = worldStateScenario();
    $otherWorld = World::factory()->forUser($user)->create();
    $otherResident = $otherWorld->residents()->create(['assistant_id' => $world->residents()->first()->assistant_id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $this->actingAs($user)->putJson(route('worlds.sessions.residents.state.update', [$world->id, $session->id, $otherResident->id]), [
        'position' => ['x' => 0, 'y' => 0, 'z' => 0],
    ])->assertNotFound();
});
