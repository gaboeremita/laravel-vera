<?php

use App\Actions\ReconcilePassages;
use App\Actions\ResolveInventory;
use App\Models\ActivityTerms;
use App\Models\InventoryItem;
use App\Models\StartingInventory;
use App\Models\StartingInventoryItem;
use App\Models\WorldSession;
use App\Models\WorldSessionObject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stockObject(array $scenario, array $entries): void
{
    [, , , $region] = $scenario;
    $starting = StartingInventory::factory()->forObject($region->id, 'pool-lounger-1')->create(['world_id' => $region->world_id]);
    foreach ($entries as [$item, $quantity, $takeable]) {
        StartingInventoryItem::factory()->create(['starting_inventory_id' => $starting->id, 'item_id' => $item->id, 'quantity' => $quantity, 'takeable' => $takeable]);
    }
}

function takeFrom($test, array $scenario, int $itemId)
{
    [$user, , , $region, , $session] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.objects.take', [$region->world_id, $session->id, 'pool-lounger-1']), ['regionId' => $region->id, 'itemId' => $itemId]);
}

it('saves an object\'s starting inventory for objects in the layout only', function () {
    [$user, , , $region] = worldStateScenario();
    $potion = worldItem($region, ['name' => 'Potion']);

    $this->actingAs($user)->putJson(route('worlds.regions.objects.starting-inventory.update', [$region->world_id, $region->id, 'pool-lounger-1']), [
        'credits' => null,
        'items' => [['itemId' => $potion->id, 'quantity' => 2, 'takeable' => true]],
    ])->assertOk()->assertJsonPath('items.0.takeable', true)->assertJsonPath('credits', null);

    $this->putJson(route('worlds.regions.objects.starting-inventory.update', [$region->world_id, $region->id, 'no-such-object']), ['credits' => 0, 'items' => []])
        ->assertJsonValidationErrors('object');
});

it('lets the player take one at a time until none is left', function () {
    $scenario = inventoryScenario();
    [$user, , , $region, , $session, $player] = $scenario;
    $potion = worldItem($region, ['name' => 'Potion']);
    stockObject($scenario, [[$potion, 2, true]]);

    takeFrom($this, $scenario, $potion->id)->assertOk()->assertJsonPath('changes.items.0.delta', 1);
    $this->getJson(route('worlds.sessions.objects.show', [$region->world_id, $session->id, 'pool-lounger-1']).'?regionId='.$region->id)
        ->assertJsonPath('takeable.0.quantity', 1);
    takeFrom($this, $scenario, $potion->id)->assertOk();
    takeFrom($this, $scenario, $potion->id)->assertUnprocessable();

    expect($player->items()->first()->quantity)->toBe(2);
});

it('never runs out of an unlimited amount and refuses what cannot be taken', function () {
    $scenario = inventoryScenario();
    [, , , $region] = $scenario;
    $water = worldItem($region, ['name' => 'Water']);
    $coin = worldItem($region, ['name' => 'Coin']);
    stockObject($scenario, [[$water, null, true], [$coin, 5, false]]);

    foreach (range(1, 3) as $ignored) {
        takeFrom($this, $scenario, $water->id)->assertOk();
    }
    takeFrom($this, $scenario, $coin->id)->assertUnprocessable();
});

it('restores the configured amount in a new session', function () {
    $scenario = inventoryScenario();
    [, , , $region, , $session] = $scenario;
    $potion = worldItem($region);
    stockObject($scenario, [[$potion, 1, true]]);
    takeFrom($this, $scenario, $potion->id)->assertOk();

    $fresh = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $region->id]);

    expect(app(ResolveInventory::class)->forObject($fresh, $region, 'pool-lounger-1')->items()->first()->quantity)->toBe(1);
});

it('removes what was configured on an object that leaves the layout', function () {
    $scenario = inventoryScenario();
    [, , , $region, , $session] = $scenario;
    stockObject($scenario, [[worldItem($region), 1, true]]);
    app(ResolveInventory::class)->forObject($session, $region, 'pool-lounger-1');
    ActivityTerms::factory()->create(['region_id' => $region->id, 'object_id' => 'pool-lounger-1', 'activity_id' => 'recline']);
    WorldSessionObject::factory()->passable()->create(['world_session_id' => $session->id, 'region_id' => $region->id]);

    $layout = $region->layout;
    $layout['objects'] = [];
    $region->update(['layout' => $layout]);
    app(ReconcilePassages::class)->handle($region->fresh());

    expect(StartingInventory::where('region_id', $region->id)->count())->toBe(0)
        ->and($session->inventories()->where('region_id', $region->id)->count())->toBe(0)
        ->and(ActivityTerms::count())->toBe(0)
        ->and(WorldSessionObject::count())->toBe(0)
        ->and(InventoryItem::count())->toBe(0);
});
