<?php

use App\Models\Conversation;
use App\Models\Region;
use App\Models\User;
use App\Models\WorldSessionResident;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('unlocks the session with the creator password and keeps the first unlock time', function () {
    [$user, , , $region, , $session] = worldStateScenario(fakeReply: false);
    $user->update(['creator_password' => 'open sesame']);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.unlock', [$region->world_id, $session->id]), ['password' => 'open sesame'])->assertNoContent();
    $unlockedAt = $session->fresh()->creator_mode_at;
    expect($unlockedAt)->not->toBeNull();

    $this->travel(5)->minutes();
    $this->actingAs($user)->postJson(route('worlds.sessions.creator.unlock', [$region->world_id, $session->id]), ['password' => 'open sesame'])->assertNoContent();

    expect($session->fresh()->creator_mode_at->equalTo($unlockedAt))->toBeTrue();
    $this->actingAs($user)->getJson(route('worlds.sessions.index', $region->world_id))->assertJsonPath('0.creatorUnlocked', true);
});

it('stays locked with a wrong password', function () {
    [$user, , , $region, , $session] = worldStateScenario(fakeReply: false);
    $user->update(['creator_password' => 'open sesame']);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.unlock', [$region->world_id, $session->id]), ['password' => 'guess'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'That password is wrong.');

    expect($session->fresh()->creator_mode_at)->toBeNull();
});

it('asks for a creator password in Settings when none is set', function () {
    [$user, , , $region, , $session] = worldStateScenario(fakeReply: false);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.unlock', [$region->world_id, $session->id]), ['password' => 'anything'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Set a creator password in Settings first.');
});

it('keeps conversation creator mode and the menu apart', function () {
    [$user, , $conversation, $region, , $session] = worldStateScenario(fakeReply: false);
    $user->update(['creator_password' => 'open sesame']);
    $conversation->update(['world_session_id' => $session->id]);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.unlock', [$region->world_id, $session->id]), ['password' => 'open sesame'])->assertNoContent();
    expect(Conversation::where('world_session_id', $session->id)->whereNotNull('creator_mode_at')->exists())->toBeFalse();

    $otherSession = $session->replicate(['creator_mode_at']);
    $otherSession->save();
    $conversation->update(['world_session_id' => $otherSession->id, 'creator_mode_at' => now()]);

    $this->actingAs($user)->getJson(route('worlds.sessions.creator.show', [$region->world_id, $otherSession->id]))->assertForbidden();
});

it('refuses the overview of a locked session', function () {
    [$user, , , $region, , $session] = worldStateScenario(fakeReply: false);

    $this->actingAs($user)->getJson(route('worlds.sessions.creator.show', [$region->world_id, $session->id]))
        ->assertForbidden()
        ->assertJsonPath('message', 'Unlock the Creator menu first.');
});

it('hides another user\'s session', function () {
    [, , , $region, , $session] = worldStateScenario(fakeReply: false);
    $session->update(['creator_mode_at' => now()]);
    $stranger = User::factory()->create();
    $region->world->users()->attach($stranger);

    $this->actingAs($stranger)->getJson(route('worlds.sessions.creator.show', [$region->world_id, $session->id]))->assertNotFound();
});

it('describes the world for the menu', function () {
    [$user, , , $region, $resident, $session] = worldStateScenario(fakeReply: false);
    $session->update(['creator_mode_at' => now()]);
    $secondRegion = Region::factory()->withLayout()->create(['world_id' => $region->world_id, 'name' => 'Docks']);
    WorldSessionResident::create(['world_session_id' => $session->id, 'world_resident_id' => $resident->id, 'region_id' => $region->id, 'position' => ['x' => -2, 'y' => 0, 'z' => 8]]);
    $quest = worldQuest($region->world);
    $session->questRuns()->create(['quest_id' => $quest->id, 'run' => 1, 'status' => 'active']);
    $fact = worldFact($resident, ['topic' => 'the vault code']);
    $item = worldItem($region, ['name' => 'Lantern']);

    $response = $this->actingAs($user)->getJson(route('worlds.sessions.creator.show', [$region->world_id, $session->id]))->assertSuccessful();

    $response->assertJsonCount(2, 'regions')
        ->assertJsonPath('regions.0.zones.1.id', 'vocal-booth')
        ->assertJsonPath('regions.0.zones.1.parentId', 'studio')
        ->assertJsonPath('regions.0.passages.0.id', 'studio-door')
        ->assertJsonPath('regions.0.objects.0.id', 'pool-lounger-1')
        ->assertJsonPath('regions.0.objects.0.activities.0.id', 'recline')
        ->assertJsonPath('regions.1.name', 'Docks')
        ->assertJsonPath('residents.0.id', $resident->id)
        ->assertJsonPath('residents.0.regionId', $region->id)
        ->assertJsonPath('residents.0.zoneName', 'Vocal booth')
        ->assertJsonPath('residents.0.frozen', false)
        ->assertJsonPath('quests.0.id', $quest->id)
        ->assertJsonPath('quests.0.status', 'active')
        ->assertJsonPath('facts.0.id', $fact->id)
        ->assertJsonPath('facts.0.known', false)
        ->assertJsonPath('items.0.id', $item->id);

    expect(Conversation::where('world_session_id', $session->id)->exists())->toBeFalse();
});
