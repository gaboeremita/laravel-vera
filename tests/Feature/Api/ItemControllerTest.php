<?php

use App\Models\ActivityTerms;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Region;
use App\Models\StartingInventory;
use App\Models\StartingInventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function itemPayload(array $overrides = []): array
{
    return [
        'name' => 'Iron key',
        'description' => 'A heavy key with a ring worn smooth.',
        'basePrice' => 12,
        'contents' => null,
        'useRequirement' => null,
        'consumedOnUse' => false,
        'releasesCredits' => 0,
        'releasesItems' => [],
        ...$overrides,
    ];
}

it('creates, lists, updates and deletes items of a world', function () {
    [$user, , , $region] = worldStateScenario();

    $id = $this->actingAs($user)->postJson(route('worlds.items.store', $region->world_id), itemPayload())
        ->assertCreated()
        ->assertJsonPath('name', 'Iron key')
        ->assertJsonPath('basePrice', 12)
        ->json('id');

    $this->getJson(route('worlds.items.index', $region->world_id))
        ->assertOk()
        ->assertJsonPath('0.name', 'Iron key')
        ->assertJsonPath('0.usage', 0);

    $this->patchJson(route('worlds.items.update', [$region->world_id, $id]), itemPayload(['name' => 'Brass key', 'contents' => 'Engraved: "Vault 3".']))
        ->assertOk()
        ->assertJsonPath('name', 'Brass key')
        ->assertJsonPath('contents', 'Engraved: "Vault 3".');

    $this->deleteJson(route('worlds.items.destroy', [$region->world_id, $id]))->assertNoContent();
    expect(Item::count())->toBe(0);
});

it('refuses a duplicate name and releases of another world\'s item', function () {
    [$user, , , $region] = worldStateScenario();
    worldItem($region, ['name' => 'Iron key']);
    $foreign = Item::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.items.store', $region->world_id), itemPayload())
        ->assertJsonValidationErrors('name');
    $this->postJson(route('worlds.items.store', $region->world_id), itemPayload(['name' => 'Lockbox', 'releasesItems' => [['itemId' => $foreign->id, 'quantity' => 1]]]))
        ->assertJsonValidationErrors('releasesItems.0.itemId');
});

it('counts where an item is used and removes it everywhere on delete', function () {
    [$user, , , $region, , $session, $player] = inventoryScenario();
    $key = worldItem($region, ['name' => 'Iron key']);
    $lockbox = worldItem($region, ['name' => 'Lockbox', 'releases_items' => [['itemId' => $key->id, 'quantity' => 1]]]);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $key->id]);
    StartingInventoryItem::factory()->create(['starting_inventory_id' => StartingInventory::factory()->create(['world_id' => $region->world_id])->id, 'item_id' => $key->id]);
    $terms = ActivityTerms::factory()->create(['region_id' => $region->id, 'required_item_id' => $key->id, 'gives_items' => [['itemId' => $key->id, 'quantity' => 1]]]);

    $this->actingAs($user)->getJson(route('worlds.items.index', $region->world_id))
        ->assertJsonPath('0.name', 'Iron key')
        ->assertJsonPath('0.usage', 4);

    $this->deleteJson(route('worlds.items.destroy', [$region->world_id, $key->id]))->assertNoContent();

    expect($player->items()->count())->toBe(0)
        ->and(StartingInventoryItem::count())->toBe(0)
        ->and($terms->fresh())->required_item_id->toBeNull()->gives_items->toBe([])
        ->and($lockbox->fresh()->releases_items)->toBe([]);
});

it('hides another user\'s world items', function () {
    $region = Region::factory()->withLayout()->create();
    $item = worldItem($region);

    $this->actingAs(User::factory()->create())->getJson(route('worlds.items.index', $region->world_id))->assertForbidden();
    $this->patchJson(route('worlds.items.update', [$region->world_id, $item->id]), itemPayload())->assertForbidden();
});
