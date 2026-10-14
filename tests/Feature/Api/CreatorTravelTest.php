<?php

use App\Actions\Quests\SyncSessionQuests;
use App\Events\Quests\PlayerEnteredRegion;
use App\Models\Region;
use App\Models\WorldSessionResident;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function creatorTravelScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, , , $region, , $session] = $scenario;
    $session->update(['creator_mode_at' => now(), 'position' => ['x' => 5, 'y' => 0, 'z' => -5]]);
    $docks = Region::factory()->withLayout()->create(['world_id' => $region->world_id, 'name' => 'Docks']);

    return [...$scenario, $docks];
}

it('moves the session and its followers to a zone in another region', function () {
    Event::fake([PlayerEnteredRegion::class]);
    [$user, , , $region, $resident, $session, $docks] = creatorTravelScenario();

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.travel', [$region->world_id, $session->id]), [
        'regionId' => $docks->id,
        'zoneId' => 'vocal-booth',
        'followerIds' => [$resident->id],
    ])->assertSuccessful()
        ->assertJsonPath('regionId', $docks->id)
        ->assertJsonPath('landInZone', 'vocal-booth')
        ->assertJsonPath('position', ['x' => -2.0, 'y' => -2.0, 'z' => 8.0]);

    expect($session->fresh()->region_id)->toBe($docks->id)
        ->and(WorldSessionResident::where('world_resident_id', $resident->id)->value('region_id'))->toBe($docks->id);
    Event::assertDispatched(PlayerEnteredRegion::class, fn (PlayerEnteredRegion $event) => $event->regionId === $docks->id);
});

it('counts a jump into a zone as entering it', function () {
    [$user, , , $region, , $session] = creatorTravelScenario();
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('booth', ['when' => ['enterZone' => ['region' => $region->id, 'zone' => 'vocal-booth']]]),
        QuestFactory::beat('later', ['requires' => ['booth']]),
    ]]);
    app(SyncSessionQuests::class)->handle($session->fresh());

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.travel', [$region->world_id, $session->id]), ['regionId' => $region->id, 'zoneId' => 'vocal-booth'])->assertSuccessful();

    expect($session->questRuns()->latest('id')->first()->finishedBeats())->toBe(['booth']);
});

it('arrives at a passage and at the world spawn', function () {
    [$user, , , $region, , $session, $docks] = creatorTravelScenario();
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.travel', [$region->world_id, $session->id]), ['regionId' => $docks->id, 'passageId' => 'terrace-gate'])
        ->assertSuccessful()
        ->assertJsonPath('position', ['x' => 5, 'y' => 0, 'z' => -8.5])
        ->assertJsonPath('landInZone', null);

    $this->postJson(route('worlds.sessions.creator.travel', [$region->world_id, $session->id]), ['spawn' => true])
        ->assertSuccessful()
        ->assertJsonPath('regionId', $region->id)
        ->assertJsonPath('position', ['x' => -5, 'y' => 0, 'z' => 1.5]);
});

it('refuses unknown zones and passages, and followers elsewhere', function () {
    [$user, , , $region, $resident, $session, $docks] = creatorTravelScenario();
    WorldSessionResident::create(['world_session_id' => $session->id, 'world_resident_id' => $resident->id, 'region_id' => $docks->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0]]);
    $route = route('worlds.sessions.creator.travel', [$region->world_id, $session->id]);

    $this->actingAs($user)->postJson($route, ['regionId' => $docks->id, 'zoneId' => 'nowhere'])->assertNotFound();
    $this->postJson($route, ['regionId' => $docks->id, 'passageId' => 'nowhere'])->assertNotFound();
    $this->postJson($route, ['regionId' => $docks->id, 'zoneId' => 'studio', 'followerIds' => [$resident->id]])->assertUnprocessable();
});

it('refuses travel in a locked session', function () {
    [$user, , , $region, , $session, $docks] = creatorTravelScenario();
    $session->update(['creator_mode_at' => null]);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.travel', [$region->world_id, $session->id]), ['regionId' => $docks->id, 'zoneId' => 'studio'])->assertForbidden();
});
