<?php

use App\Models\Conversation;
use App\Models\User;
use App\Models\World;
use App\Models\WorldSession;
use App\Models\WorldUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists only the requesters sessions for a world, most recently active first', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();

    $older = WorldSession::factory()->for($worldUser)->create(['updated_at' => now()->subDay()]);
    $newer = WorldSession::factory()->for($worldUser)->create(['updated_at' => now()]);

    $otherUser = User::factory()->create();
    $otherWorld = World::factory()->forUser($otherUser)->create();
    $otherWorldUser = WorldUser::where('world_id', $otherWorld->id)->where('user_id', $otherUser->id)->firstOrFail();
    WorldSession::factory()->for($otherWorldUser)->create();

    $response = $this->actingAs($user)->getJson(route('worlds.sessions.index', $world))
        ->assertSuccessful();

    $response->assertJsonPath('0.id', $newer->id)
        ->assertJsonPath('1.id', $older->id)
        ->assertJsonCount(2);
});

it('returns an empty list for a world with no sessions yet', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();

    $this->actingAs($user)->getJson(route('worlds.sessions.index', $world))
        ->assertSuccessful()
        ->assertJsonCount(0);
});

it('returns 404 listing sessions for a world the requester has no access to', function () {
    $world = World::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('worlds.sessions.index', $world))->assertNotFound();
});

it('creates a new session defaulting to title New session and null position', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();

    $response = $this->actingAs($user)->postJson(route('worlds.sessions.store', $world))
        ->assertCreated()
        ->assertJsonPath('title', 'New session')
        ->assertJsonPath('position', null);

    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    expect(WorldSession::where('world_user_id', $worldUser->id)->count())->toBe(1);
});

it('returns 404 creating a session for a world the requester has no access to', function () {
    $world = World::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.sessions.store', $world))->assertNotFound();
});

it('updates and returns a sessions position', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create();

    $this->actingAs($user)->putJson(route('worlds.sessions.position.update', [$world, $session]), [
        'position' => ['x' => 1, 'y' => 2, 'z' => 3],
    ])->assertSuccessful()
        ->assertJsonPath('position', ['x' => 1, 'y' => 2, 'z' => 3]);

    expect($session->fresh()->position)->toBe(['x' => 1, 'y' => 2, 'z' => 3]);
});

it('rejects malformed session positions', function (array $payload) {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create();

    $this->actingAs($user)->putJson(route('worlds.sessions.position.update', [$world, $session]), $payload)
        ->assertUnprocessable();
})->with([
    'missing position' => [[]],
    'scalar position' => [['position' => 'wall']],
    'missing coordinate' => [['position' => ['x' => 1, 'y' => 2]]],
    'non-numeric coordinate' => [['position' => ['x' => 1, 'y' => 'roof', 'z' => 3]]],
    'unexpected coordinate' => [['position' => ['x' => 1, 'y' => 2, 'z' => 3, 'rotation' => 90]]],
]);

it('returns 404 updating a session from another world owned by the requester', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $otherWorld = World::factory()->forUser($user)->create();
    $otherWorldUser = WorldUser::where('world_id', $otherWorld->id)->where('user_id', $user->id)->firstOrFail();
    $otherSession = WorldSession::factory()->for($otherWorldUser)->create();

    $this->actingAs($user)->putJson(route('worlds.sessions.position.update', [$world, $otherSession]), [
        'position' => ['x' => 1, 'y' => 2, 'z' => 3],
    ])->assertNotFound();
});

it('returns 404 updating position for a session the requester does not own', function () {
    $owner = User::factory()->create();
    $world = World::factory()->forUser($owner)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $owner->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create();

    $intruder = User::factory()->create();

    $this->actingAs($intruder)->putJson(route('worlds.sessions.position.update', [$world, $session]), [
        'position' => ['x' => 1, 'y' => 2, 'z' => 3],
    ])->assertNotFound();
});

it('permanently deletes a session and cascades its conversations', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create();
    $conversation = Conversation::factory()->forWorldSession($session)->create();

    $this->actingAs($user)->deleteJson(route('worlds.sessions.destroy', [$world, $session]))->assertNoContent();

    expect(WorldSession::find($session->id))->toBeNull();
    expect(Conversation::find($conversation->id))->toBeNull();
});

it('renames a session', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create();

    $this->actingAs($user)->patchJson(route('worlds.sessions.update', [$world, $session]), [
        'title' => 'Renamed session',
    ])->assertSuccessful()->assertJsonPath('title', 'Renamed session');

    expect($session->fresh()->title)->toBe('Renamed session');
});

it('rejects a rename title over 100 characters', function () {
    $user = User::factory()->create();
    $world = World::factory()->forUser($user)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create();

    $this->actingAs($user)->patchJson(route('worlds.sessions.update', [$world, $session]), [
        'title' => str_repeat('a', 101),
    ])->assertUnprocessable();
});

it('returns 404 deleting a session the requester does not own', function () {
    $owner = User::factory()->create();
    $world = World::factory()->forUser($owner)->create();
    $worldUser = WorldUser::where('world_id', $world->id)->where('user_id', $owner->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create();

    $intruder = User::factory()->create();

    $this->actingAs($intruder)->deleteJson(route('worlds.sessions.destroy', [$world, $session]))->assertNotFound();
});
