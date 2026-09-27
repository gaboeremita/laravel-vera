<?php

use App\Models\Conversation;
use App\Models\Assistant;
use App\Models\Region;
use App\Models\User;
use App\Models\World;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
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

it('creates a new session in front of the spawn point', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create();
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);

    $this->actingAs($user)->postJson(route('worlds.sessions.store', $region->world_id))
        ->assertCreated()
        ->assertJsonPath('title', 'New session')
        ->assertJsonPath('region_id', $region->id)
        ->assertJsonPath('position', ['x' => -5, 'y' => 0, 'z' => 1.5]);
});

it('refuses to start a session while the world has no spawn point', function () {
    $user = User::factory()->create();
    $region = Region::factory()->forUser($user)->withLayout()->create();

    $this->actingAs($user)->postJson(route('worlds.sessions.store', $region->world_id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('world');

    expect(WorldSession::count())->toBe(0);
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

/**
 * Two linked regions of one world, a session in the first, and a resident there.
 *
 * @return array{0: User, 1: Region, 2: Region, 3: WorldSession, 4: \App\Models\WorldResident}
 */
function linkedRegionsScenario(): array
{
    $user = User::factory()->create();
    $lobby = Region::factory()->forUser($user)->withLayout()->create();
    $penthouse = Region::factory()->withLayout()->create(['world_id' => $lobby->world_id]);
    app(App\Actions\LinkPassages::class)->link($lobby, 'studio-door', $penthouse, 'terrace-gate');
    $worldUser = WorldUser::where('world_id', $lobby->world_id)->where('user_id', $user->id)->firstOrFail();
    $session = WorldSession::factory()->for($worldUser)->create(['region_id' => $lobby->id, 'position' => ['x' => -5, 'y' => 0, 'z' => 3]]);
    $resident = $lobby->residents()->create(['assistant_id' => Assistant::factory()->create()->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    return [$user, $lobby, $penthouse, $session, $resident];
}

it('travels to the linked passage with the residents following the player', function () {
    [$user, $lobby, $penthouse, $session, $follower] = linkedRegionsScenario();
    $stayer = $lobby->residents()->create(['assistant_id' => Assistant::factory()->create()->id, 'position' => ['x' => 1, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $this->actingAs($user)->postJson(route('worlds.sessions.travel', [$lobby->world_id, $session]), ['regionId' => $lobby->id, 'passageId' => 'studio-door', 'followerIds' => [$follower->id]])
        ->assertSuccessful()
        ->assertJsonPath('regionId', $penthouse->id)
        ->assertJsonPath('position', ['x' => 5, 'y' => 0, 'z' => -8.5])
        ->assertJsonPath('followers.'.$follower->id.'.position.z', -8.5);

    expect($session->fresh()->region_id)->toBe($penthouse->id)
        ->and($session->fresh()->position)->toBe(['x' => 5, 'y' => 0, 'z' => -8.5])
        ->and(WorldSessionResident::where('world_resident_id', $follower->id)->firstOrFail()->region_id)->toBe($penthouse->id)
        ->and(WorldSessionResident::where('world_resident_id', $stayer->id)->exists())->toBeFalse();

    $this->actingAs($user)->getJson(route('worlds.sessions.index', $lobby->world_id))
        ->assertJsonPath('0.regionId', $penthouse->id)
        ->assertJsonPath('0.residentStates.'.$follower->id.'.regionId', $penthouse->id);
});

it('travels back the other way through the same link', function () {
    [$user, $lobby, $penthouse, $session] = linkedRegionsScenario();
    $session->update(['region_id' => $penthouse->id]);

    $this->actingAs($user)->postJson(route('worlds.sessions.travel', [$lobby->world_id, $session]), ['regionId' => $penthouse->id, 'passageId' => 'terrace-gate'])
        ->assertSuccessful()
        ->assertJsonPath('regionId', $lobby->id)
        ->assertJsonPath('position', ['x' => -5, 'y' => 0, 'z' => 1.5]);
});

it('does not travel through an unlinked passage or from a region the session is not in', function (Closure $payload) {
    [$user, $lobby, $penthouse, $session] = linkedRegionsScenario();

    $this->actingAs($user)->postJson(route('worlds.sessions.travel', [$lobby->world_id, $session]), $payload($lobby, $penthouse))
        ->assertUnprocessable();

    expect($session->fresh()->region_id)->toBe($lobby->id);
})->with([
    'unlinked passage' => [fn (Region $lobby) => ['regionId' => $lobby->id, 'passageId' => 'terrace-gate']],
    'another region' => [fn (Region $lobby, Region $penthouse) => ['regionId' => $penthouse->id, 'passageId' => 'terrace-gate']],
]);

it('does not bring along a resident who is in another region', function () {
    [$user, $lobby, $penthouse, $session] = linkedRegionsScenario();
    $elsewhere = $penthouse->residents()->create(['assistant_id' => Assistant::factory()->create()->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $this->actingAs($user)->postJson(route('worlds.sessions.travel', [$lobby->world_id, $session]), ['regionId' => $lobby->id, 'passageId' => 'studio-door', 'followerIds' => [$elsewhere->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('followerIds');

    expect($session->fresh()->region_id)->toBe($lobby->id);
});

it('resumes a session whose region was deleted in front of the spawn point', function () {
    [$user, $lobby, $penthouse, $session] = linkedRegionsScenario();
    $lobby->world->update(['spawn_region_id' => $lobby->id, 'spawn_passage_id' => 'studio-door']);
    $session->update(['region_id' => $penthouse->id]);
    $penthouse->delete();

    $this->actingAs($user)->postJson(route('worlds.sessions.resume', [$lobby->world_id, $session]))
        ->assertSuccessful()
        ->assertJsonPath('regionId', $lobby->id)
        ->assertJsonPath('position', ['x' => -5, 'y' => 0, 'z' => 1.5]);
});

it('cannot resume a session whose region was deleted while there is no spawn point', function () {
    [$user, $lobby, $penthouse, $session] = linkedRegionsScenario();
    $session->update(['region_id' => $penthouse->id]);
    $penthouse->delete();

    $this->actingAs($user)->postJson(route('worlds.sessions.resume', [$lobby->world_id, $session]))->assertUnprocessable();
});

it('keeps the region a moved resident belongs to when their current region is deleted', function () {
    [$user, $lobby, $penthouse, $session, $resident] = linkedRegionsScenario();
    WorldSessionResident::factory()->create(['world_session_id' => $session->id, 'world_resident_id' => $resident->id, 'region_id' => $penthouse->id]);

    $penthouse->delete();

    expect(WorldSessionResident::count())->toBe(0)
        ->and($resident->fresh()->region_id)->toBe($lobby->id);
});
