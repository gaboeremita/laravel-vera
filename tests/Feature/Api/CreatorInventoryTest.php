<?php

use App\Actions\Quests\SyncSessionQuests;
use App\Enums\InventoryHolder;
use App\Models\CreditTransaction;
use App\Models\Inventory;
use App\Models\ItemTransfer;
use App\Models\Region;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function creatorInventoryScenario(int $credits = 100): array
{
    $scenario = inventoryScenario($credits);
    [, , , $region, , $session] = $scenario;
    $session->update(['creator_mode_at' => now()]);

    return $scenario;
}

it('adds credits, and takes no more than the balance', function () {
    [$user, , , $region, , $session, $player] = creatorInventoryScenario(100);
    $route = route('worlds.sessions.creator.credits.store', [$region->world_id, $session->id]);

    $this->actingAs($user)->postJson($route, ['amount' => 100])->assertSuccessful()->assertJsonPath('inventory.credits', 200);
    $this->postJson($route, ['amount' => -1000])->assertSuccessful()->assertJsonPath('inventory.credits', 0);

    expect($player->fresh()->credits)->toBe(0)
        ->and(CreditTransaction::where('by_creator', false)->exists())->toBeFalse()
        ->and(CreditTransaction::count())->toBe(2);
});

it('grabs any quantity of an item made in the session, and a beat about holding it finishes', function () {
    [$user, , , $region, , $session, $player] = creatorInventoryScenario();
    $made = worldItem($region, ['name' => 'Charm', 'world_session_id' => $session->id]);
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('hold', ['when' => ['has' => ['item' => $made->id, 'atLeast' => 3]]]),
        QuestFactory::beat('later', ['requires' => ['hold']]),
    ]]);
    app(SyncSessionQuests::class)->handle($session->fresh());

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.inventories.grant', [$region->world_id, $session->id]), ['holder' => ['type' => 'player'], 'itemId' => $made->id, 'quantity' => 3])
        ->assertSuccessful()
        ->assertJsonPath('inventory.items.0.quantity', 3);

    expect(ItemTransfer::sole()->by_creator)->toBeTrue()
        ->and($session->questRuns()->latest('id')->first()->finishedBeats())->toBe(['hold']);
});

it('takes an item from an object in another region, creating its inventory when missing', function () {
    [$user, , , $region, , $session] = creatorInventoryScenario();
    $docks = Region::factory()->withLayout()->create(['world_id' => $region->world_id]);
    $lantern = worldItem($region, ['name' => 'Lantern']);
    $holder = ['type' => 'object', 'regionId' => $docks->id, 'objectId' => 'pool-lounger-1'];

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.inventories.grant', [$region->world_id, $session->id]), ['holder' => $holder, 'itemId' => $lantern->id, 'quantity' => 1])->assertSuccessful();
    expect(Inventory::where('holder', InventoryHolder::Object)->where('region_id', $docks->id)->sole()->items()->sole()->takeable)->toBeTrue();

    $this->postJson(route('worlds.sessions.creator.inventories.move', [$region->world_id, $session->id]), ['from' => $holder, 'to' => ['type' => 'player'], 'itemId' => $lantern->id, 'quantity' => 1])
        ->assertSuccessful()
        ->assertJsonPath('inventory.items.0.name', 'Lantern');

    $this->getJson(route('worlds.sessions.creator.inventories.show', [$region->world_id, $session->id, 'holder' => $holder]))
        ->assertSuccessful()
        ->assertJsonCount(0, 'items');
});

it('gives a resident an item from nothing', function () {
    [$user, , , $region, $resident, $session, , $residentInventory] = creatorInventoryScenario();
    $bread = worldItem($region, ['name' => 'Bread']);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.inventories.grant', [$region->world_id, $session->id]), ['holder' => ['type' => 'resident', 'residentId' => $resident->id], 'itemId' => $bread->id, 'quantity' => 2])->assertSuccessful();

    expect($residentInventory->items()->sole()->quantity)->toBe(2);
});

it('refuses to move more than the holder has', function () {
    [$user, , , $region, $resident, $session] = creatorInventoryScenario();
    $bread = worldItem($region, ['name' => 'Bread']);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.inventories.move', [$region->world_id, $session->id]), [
        'from' => ['type' => 'resident', 'residentId' => $resident->id],
        'to' => ['type' => 'player'],
        'itemId' => $bread->id,
        'quantity' => 1,
    ])->assertUnprocessable();
});
