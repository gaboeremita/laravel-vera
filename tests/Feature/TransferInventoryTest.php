<?php

use App\Actions\ResolveInventory;
use App\Actions\StockSession;
use App\Actions\TransferInventory;
use App\Exceptions\InsufficientInventory;
use App\Models\CreditTransaction;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\StartingInventory;
use App\Models\StartingInventoryItem;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('moves items and credits between two inventories and records the credits', function () {
    [$user, $assistant, , $region, , , $player, $resident] = inventoryScenario(playerCredits: 100);
    $bread = worldItem($region, ['name' => 'Bread']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $bread->id, 'quantity' => 3]);

    app(TransferInventory::class)->handle($player, $resident, 40, [$bread->id => 2], 'gift');

    expect($player->fresh()->credits)->toBe(60)
        ->and($resident->fresh()->credits)->toBeNull()
        ->and($player->items()->first()->quantity)->toBe(1)
        ->and($resident->items()->first()->quantity)->toBe(2);

    expect(CreditTransaction::sole())
        ->amount->toBe(40)
        ->reason->toBe('gift')
        ->from_name->toBe($user->name)
        ->to_name->toBe($assistant->name);
});

it('never runs out of an unlimited amount', function () {
    [, , , $region, , , $player, $resident] = inventoryScenario();
    $bread = worldItem($region);
    InventoryItem::factory()->unlimited()->create(['inventory_id' => $resident->id, 'item_id' => $bread->id]);

    app(TransferInventory::class)->handle($resident, $player, 1000, [$bread->id => 50], 'gift');

    expect($resident->fresh()->credits)->toBeNull()
        ->and($resident->items()->first()->quantity)->toBeNull()
        ->and($player->fresh()->credits)->toBe(1100)
        ->and($player->items()->first()->quantity)->toBe(50);
});

it('refuses when the giver is short and changes nothing', function () {
    [, , , $region, , , $player, $resident] = inventoryScenario(playerCredits: 10);
    $key = worldItem($region, ['name' => 'Iron key']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $key->id, 'quantity' => 1]);

    expect(fn () => app(TransferInventory::class)->handle($player, $resident, 5, [$key->id => 2], 'gift'))
        ->toThrow(InsufficientInventory::class, 'has only 1 Iron key');
    expect(fn () => app(TransferInventory::class)->handle($player, $resident, 20, [], 'gift'))
        ->toThrow(InsufficientInventory::class, 'has only 10 credits');

    expect($player->fresh()->credits)->toBe(10)
        ->and($player->items()->first()->quantity)->toBe(1)
        ->and($resident->items()->count())->toBe(0)
        ->and(CreditTransaction::count())->toBe(0);
});

it('removes an item row when its quantity reaches zero', function () {
    [, , , $region, , , $player, $resident] = inventoryScenario();
    $key = worldItem($region);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $key->id, 'quantity' => 1]);

    app(TransferInventory::class)->handle($player, $resident, 0, [$key->id => 1], 'gift');

    expect($player->items()->count())->toBe(0);
});

it('creates and destroys things when a side is outside every inventory', function () {
    [, , , $region, , , $player] = inventoryScenario(playerCredits: 0);
    $coin = worldItem($region);

    app(TransferInventory::class)->handle(null, $player, 25, [$coin->id => 2], 'Lockbox');
    app(TransferInventory::class)->handle($player, null, 0, [$coin->id => 1], 'used');

    expect($player->fresh()->credits)->toBe(25)
        ->and($player->items()->first()->quantity)->toBe(1)
        ->and(CreditTransaction::sole()->from_name)->toBe('Lockbox');
});

it('stocks a new session with a copy of the starting inventories', function () {
    [, , , $region, $resident, $session] = inventoryScenario();
    $session->inventories()->delete();
    $bread = worldItem($region);
    $player = StartingInventory::factory()->forPlayer()->create(['world_id' => $region->world_id, 'credits' => 30]);
    StartingInventoryItem::factory()->create(['starting_inventory_id' => $player->id, 'item_id' => $bread->id, 'quantity' => 2]);
    $vendor = StartingInventory::factory()->forResident($resident)->unlimited()->create();
    StartingInventoryItem::factory()->unlimited()->forSale()->create(['starting_inventory_id' => $vendor->id, 'item_id' => $bread->id]);

    app(StockSession::class)->handle($session);
    $player->update(['credits' => 999]);

    $stockedPlayer = app(ResolveInventory::class)->forPlayer($session);
    $stockedVendor = app(ResolveInventory::class)->forResident($session, $resident);
    expect($stockedPlayer->credits)->toBe(30)
        ->and($stockedPlayer->items()->first()->quantity)->toBe(2)
        ->and($stockedVendor->credits)->toBeNull()
        ->and($stockedVendor->items()->first())->quantity->toBeNull()->for_sale->toBeTrue();
});

it('copies a starting inventory the first time an older session needs it', function () {
    [, , , $region, $resident, $session] = inventoryScenario();
    $session->inventories()->delete();
    $bread = worldItem($region);
    $starting = StartingInventory::factory()->forResident($resident)->create();
    StartingInventoryItem::factory()->create(['starting_inventory_id' => $starting->id, 'item_id' => $bread->id, 'quantity' => 3]);

    $first = app(ResolveInventory::class)->forResident($session, $resident);
    $second = app(ResolveInventory::class)->forResident($session, $resident);

    expect($first->items()->first()->quantity)->toBe(3)
        ->and($second->id)->toBe($first->id)
        ->and(app(ResolveInventory::class)->forPlayer($session)->credits)->toBe(0);
});

it('leaves a resident\'s credits uncounted whatever their starting inventory held', function () {
    [, , , , $resident, $session] = inventoryScenario();
    $session->inventories()->delete();
    StartingInventory::factory()->forResident($resident)->create(['credits' => 12]);

    expect(app(ResolveInventory::class)->forResident($session, $resident)->credits)->toBeNull();
});

it('starts a resident with no starting inventory with uncounted credits', function () {
    [, , , , $resident, $session] = inventoryScenario();
    $session->inventories()->delete();

    expect(app(ResolveInventory::class)->forResident($session, $resident)->credits)->toBeNull()
        ->and(app(ResolveInventory::class)->forPlayer($session)->credits)->toBe(0);
});

it('starts a new session through the API with the configured inventory', function () {
    [$user, , , $region, , $session] = inventoryScenario();
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);
    StartingInventory::factory()->forPlayer()->create(['world_id' => $region->world_id, 'credits' => 75]);

    $response = $this->actingAs($user)->postJson(route('worlds.sessions.store', $region->world_id))->assertCreated();

    expect(app(ResolveInventory::class)->forPlayer(WorldSession::find($response->json('id')))->credits)->toBe(75)
        ->and(Inventory::where('world_session_id', $session->id)->count())->toBe(2);
});
