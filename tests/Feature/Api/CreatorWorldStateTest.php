<?php

use App\Actions\Quests\SyncSessionQuests;
use App\Enums\RevealSource;
use App\Models\ActivityTerms;
use App\Models\Inventory;
use App\Models\KnownFact;
use App\Models\Region;
use App\Models\RevealAttempt;
use App\Models\WorldSessionObject;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function creatorWorldScenario(): array
{
    $scenario = inventoryScenario(50);
    [, , , , , $session] = $scenario;
    $session->update(['creator_mode_at' => now()]);

    return $scenario;
}

it('makes an object in another region passable and blocking again', function () {
    [$user, , , $region, , $session] = creatorWorldScenario();
    $docks = Region::factory()->withLayout()->create(['world_id' => $region->world_id]);
    $route = route('worlds.sessions.creator.objects.passable.update', [$region->world_id, $session->id]);

    $this->actingAs($user)->putJson($route, ['regionId' => $docks->id, 'objectId' => 'pool-lounger-1', 'passable' => true])
        ->assertSuccessful()
        ->assertJsonPath('objectState.state.passable', true);
    expect(WorldSessionObject::where('region_id', $docks->id)->sole()->has('passable'))->toBeTrue();

    $this->putJson($route, ['regionId' => $docks->id, 'objectId' => 'pool-lounger-1', 'passable' => false])->assertSuccessful();
    expect(WorldSessionObject::where('region_id', $docks->id)->sole()->has('passable'))->toBeFalse();

    $this->putJson($route, ['regionId' => $docks->id, 'objectId' => 'nowhere', 'passable' => true])->assertNotFound();
});

it('reveals a fact as the creator, and forgets it', function () {
    [$user, , , $region, $resident, $session] = creatorWorldScenario();
    $fact = worldFact($resident, ['topic' => 'the vault code', 'content' => 'It is 4471.']);
    $route = route('worlds.sessions.creator.facts.update', [$region->world_id, $session->id, $fact->id]);

    $this->actingAs($user)->putJson($route, ['known' => true])->assertSuccessful()->assertJsonPath('learnedFact.factId', $fact->id);
    expect(KnownFact::where('world_session_id', $session->id)->where('fact_id', $fact->id)->exists())->toBeTrue()
        ->and(RevealAttempt::where('source', RevealSource::Creator)->exists())->toBeTrue();

    $this->putJson($route, ['known' => false])->assertSuccessful()->assertJsonPath('learnedFact', null);
    expect(KnownFact::where('fact_id', $fact->id)->exists())->toBeFalse();
});

it('uses a paid activity from anywhere: its gifts arrive, its price and condition are skipped, and a use beat finishes', function () {
    [$user, , , $region, , $session, $player] = creatorWorldScenario();
    $towel = worldItem($region, ['name' => 'Towel']);
    $key = worldItem($region, ['name' => 'Key']);
    Inventory::factory()->forObject($region->id, 'pool-lounger-1')->create(['world_session_id' => $session->id])->items()->create(['item_id' => $towel->id, 'quantity' => 3]);
    ActivityTerms::factory()->create(['region_id' => $region->id, 'object_id' => 'pool-lounger-1', 'activity_id' => 'recline', 'responses' => [
        ['condition' => ['has' => ['item' => $key->id, 'atLeast' => 1]], 'effects' => [['type' => 'takeCredits', 'amount' => 20], ['type' => 'giveItems', 'items' => [['itemId' => $towel->id, 'quantity' => 1]]]]],
    ]]);
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('rest', ['when' => ['use' => ['region' => $region->id, 'object' => 'pool-lounger-1', 'activity' => 'recline']]]),
        QuestFactory::beat('later', ['requires' => ['rest']]),
    ]]);
    app(SyncSessionQuests::class)->handle($session->fresh());

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.activity-uses.store', [$region->world_id, $session->id]), ['regionId' => $region->id, 'objectId' => 'pool-lounger-1', 'activityId' => 'recline', 'response' => 0])
        ->assertSuccessful()
        ->assertJsonPath('allowed', true)
        ->assertJsonPath('inventory.credits', 50)
        ->assertJsonPath('inventory.items.0.name', 'Towel');

    expect($player->fresh()->credits)->toBe(50)
        ->and($session->questRuns()->latest('id')->first()->finishedBeats())->toBe(['rest']);
});

it('refuses an unknown activity', function () {
    [$user, , , $region, , $session] = creatorWorldScenario();

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.activity-uses.store', [$region->world_id, $session->id]), ['regionId' => $region->id, 'objectId' => 'pool-lounger-1', 'activityId' => 'dance'])->assertNotFound();
});
