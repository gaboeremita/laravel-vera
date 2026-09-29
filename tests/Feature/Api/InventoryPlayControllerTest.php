<?php

use App\Actions\TransferInventory;
use App\Enums\HandoverRequestStatus;
use App\Models\CreditTransaction;
use App\Models\HandoverRequest;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the player\'s credits and items', function () {
    [$user, , , $region, , $session, $player] = inventoryScenario(playerCredits: 40);
    $bread = worldItem($region, ['name' => 'Bread', 'description' => 'Still warm.']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $bread->id, 'quantity' => 2]);

    $this->actingAs($user)->getJson(route('worlds.sessions.inventory.show', [$region->world_id, $session->id]))
        ->assertOk()
        ->assertJsonPath('credits', 40)
        ->assertJsonPath('items.0.name', 'Bread')
        ->assertJsonPath('items.0.quantity', 2)
        ->assertJsonPath('items.0.description', 'Still warm.');
});

it('gives credits and items to a resident and returns the line they hear, the credits leaving only the player', function () {
    [$user, $assistant, , $region, $resident, $session, $player, $residentInventory] = inventoryScenario(playerCredits: 100);
    $lantern = worldItem($region, ['name' => 'Lantern']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $lantern->id, 'quantity' => 1]);

    $this->actingAs($user)->postJson(route('worlds.sessions.handovers.store', [$region->world_id, $session->id]), [
        'residentId' => $resident->id,
        'credits' => 50,
        'items' => [['itemId' => $lantern->id, 'quantity' => 1]],
    ])
        ->assertOk()
        ->assertJsonPath('line', "[{$user->name} hands you 50 credits and the Lantern]")
        ->assertJsonPath('inventory.credits', 50)
        ->assertJsonPath('inventory.items', [])
        ->assertJsonPath('changes.credits', -50)
        ->assertJsonPath('changes.items.0.delta', -1);

    expect($residentInventory->fresh()->credits)->toBeNull()
        ->and($residentInventory->items()->first()->item_id)->toBe($lantern->id);
});

it('refuses to give more than the player holds and moves nothing', function () {
    [$user, , , $region, $resident, $session, $player, $residentInventory] = inventoryScenario(playerCredits: 20);

    $this->actingAs($user)->postJson(route('worlds.sessions.handovers.store', [$region->world_id, $session->id]), [
        'residentId' => $resident->id,
        'credits' => 50,
        'items' => [],
    ])->assertUnprocessable()->assertJsonPath('message', "{$user->name} has only 20 credits.");

    expect($player->fresh()->credits)->toBe(20)->and(CreditTransaction::count())->toBe(0);
});

it('keeps another user\'s session out of reach', function () {
    [, , , $region, $resident, $session] = inventoryScenario();

    $this->actingAs(User::factory()->create())->getJson(route('worlds.sessions.inventory.show', [$region->world_id, $session->id]))->assertNotFound();
    $this->postJson(route('worlds.sessions.handovers.store', [$region->world_id, $session->id]), ['residentId' => $resident->id, 'credits' => 1, 'items' => []])->assertNotFound();
});

it('keeps another session of the same player apart', function () {
    [$user, , , $region, , $session, $player] = inventoryScenario(playerCredits: 70);
    $other = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $region->id]);

    $this->actingAs($user)->getJson(route('worlds.sessions.inventory.show', [$region->world_id, $other->id]))
        ->assertOk()
        ->assertJsonPath('credits', 0);
    expect($player->fresh()->credits)->toBe(70);
});

function pendingRequest(array $scenario, int $credits, array $items = [], string $reason = 'for the map'): HandoverRequest
{
    [, $assistant, $conversation, , , $session, , $residentInventory] = $scenario;

    return HandoverRequest::factory()->create([
        'world_session_id' => $session->id,
        'conversation_id' => $conversation->id,
        'inventory_id' => $residentInventory->id,
        'credits' => $credits,
        'items' => $items,
        'reason' => $reason,
    ]);
}

it('hands over credits and items together when the player accepts a request', function () {
    $scenario = inventoryScenario(playerCredits: 50);
    [$user, , , $region, , $session, $player, $residentInventory] = $scenario;
    $lantern = worldItem($region, ['name' => 'Lantern']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $lantern->id, 'quantity' => 1]);
    $request = pendingRequest($scenario, 30, [['itemId' => $lantern->id, 'quantity' => 1]]);

    $this->actingAs($user)->postJson(route('worlds.sessions.handover-requests.answer', [$region->world_id, $session->id, $request->id]), ['accept' => true])
        ->assertOk()
        ->assertJsonPath('status', 'accepted')
        ->assertJsonPath('line', "[{$user->name} agrees and hands you 30 credits and the Lantern for the map]")
        ->assertJsonPath('inventory.credits', 20);

    expect($residentInventory->fresh()->credits)->toBeNull()
        ->and($residentInventory->items()->first()->item_id)->toBe($lantern->id)
        ->and(CreditTransaction::sole()->reason)->toBe('for the map');
});

it('moves nothing when the player declines or cannot afford a request', function () {
    $scenario = inventoryScenario(playerCredits: 10);
    [$user, , , $region, , $session, $player] = $scenario;
    $declined = pendingRequest($scenario, 5);
    $unaffordable = pendingRequest($scenario, 30);

    $this->actingAs($user)->postJson(route('worlds.sessions.handover-requests.answer', [$region->world_id, $session->id, $declined->id]), ['accept' => false])
        ->assertOk()->assertJsonPath('status', 'declined')->assertJsonPath('line', "[{$user->name} declines to give you 5 credits for the map]");
    $this->postJson(route('worlds.sessions.handover-requests.answer', [$region->world_id, $session->id, $unaffordable->id]), ['accept' => true])
        ->assertOk()->assertJsonPath('status', 'unaffordable');

    expect($player->fresh()->credits)->toBe(10);
});

it('answers a request only once', function () {
    $scenario = inventoryScenario(playerCredits: 50);
    [$user, , , $region, , $session] = $scenario;
    $request = pendingRequest($scenario, 5);

    $this->actingAs($user)->postJson(route('worlds.sessions.handover-requests.answer', [$region->world_id, $session->id, $request->id]), ['accept' => true])->assertOk();
    $this->postJson(route('worlds.sessions.handover-requests.answer', [$region->world_id, $session->id, $request->id]), ['accept' => true])->assertConflict();
});

it('cancels pending requests when the conversation ends or the session is resumed', function () {
    $scenario = inventoryScenario(playerCredits: 50);
    [$user, , $conversation, $region, , $session] = $scenario;
    $ended = pendingRequest($scenario, 5);

    $this->actingAs($user)->postJson(route('worlds.sessions.conversations.handover-requests.cancel', [$region->world_id, $session->id, $conversation->id]))->assertNoContent();
    expect($ended->fresh()->status)->toBe(HandoverRequestStatus::Cancelled);
    $this->postJson(route('worlds.sessions.handover-requests.answer', [$region->world_id, $session->id, $ended->id]), ['accept' => true])->assertConflict();

    $stale = pendingRequest($scenario, 5);
    $this->postJson(route('worlds.sessions.resume', [$region->world_id, $session->id]))->assertOk();
    expect($stale->fresh()->status)->toBe(HandoverRequestStatus::Cancelled);
});

it('shows a vendor\'s goods for sale and nothing of anyone else\'s inventory', function () {
    [$user, , , $region, $resident, $session, , $residentInventory] = inventoryScenario();
    InventoryItem::factory()->forSale()->unlimited()->create(['inventory_id' => $residentInventory->id, 'item_id' => worldItem($region, ['name' => 'Bread', 'base_price' => 2])->id]);
    InventoryItem::factory()->create(['inventory_id' => $residentInventory->id, 'item_id' => worldItem($region, ['name' => 'Diary'])->id]);

    $this->actingAs($user)->getJson(route('worlds.sessions.residents.goods.index', [$region->world_id, $session->id, $resident->id]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Bread')
        ->assertJsonPath('0.quantity', null)
        ->assertJsonPath('0.basePrice', 2);

    $residentInventory->items()->update(['for_sale' => false]);
    $this->getJson(route('worlds.sessions.residents.goods.index', [$region->world_id, $session->id, $resident->id]))->assertOk()->assertJsonCount(0);
});

it('lists every credit change of the player, newest first, and keeps names of removed residents', function () {
    $scenario = inventoryScenario(playerCredits: 100);
    [$user, $assistant, , $region, $resident, $session, $player, $residentInventory] = $scenario;
    app(TransferInventory::class)->handle($player, $residentInventory, 30, [], 'for the map');
    app(TransferInventory::class)->handle($residentInventory, $player, 10, [], 'gift');

    $resident->delete();

    $this->actingAs($user)->getJson(route('worlds.sessions.credit-history.index', [$region->world_id, $session->id]))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.direction', 'in')
        ->assertJsonPath('0.counterpart', $assistant->name)
        ->assertJsonPath('1.direction', 'out')
        ->assertJsonPath('1.amount', 30)
        ->assertJsonPath('1.reason', 'for the map');
});

it('shows an empty credit history', function () {
    [$user, , , $region, , $session] = inventoryScenario();

    $this->actingAs($user)->getJson(route('worlds.sessions.credit-history.index', [$region->world_id, $session->id]))->assertOk()->assertJsonCount(0);
});
