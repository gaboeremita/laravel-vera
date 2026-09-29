<?php

use App\Actions\ResolveInventory;
use App\Enums\AssistantKind;
use App\Models\AiModel;
use App\Models\Settings;
use App\Models\StartingInventory;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves the player\'s starting credits and items, and a new session starts with them', function () {
    [$user, , , $region] = worldStateScenario();
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);
    $bread = worldItem($region, ['name' => 'Bread']);

    $this->actingAs($user)->putJson(route('worlds.starting-inventories.player.update', $region->world_id), [
        'credits' => 100,
        'items' => [['itemId' => $bread->id, 'quantity' => 3]],
    ])->assertOk()->assertJsonPath('credits', 100)->assertJsonPath('items.0.quantity', 3);

    $sessionId = $this->postJson(route('worlds.sessions.store', $region->world_id))->assertCreated()->json('id');
    $inventory = app(ResolveInventory::class)->forPlayer(WorldSession::find($sessionId));

    expect($inventory->credits)->toBe(100)
        ->and($inventory->items()->first())->item_id->toBe($bread->id)->quantity->toBe(3);
});

it('refuses unlimited credits or quantities for the player', function () {
    [$user, , , $region] = worldStateScenario();
    $bread = worldItem($region);

    $this->actingAs($user)->putJson(route('worlds.starting-inventories.player.update', $region->world_id), [
        'credits' => null,
        'items' => [['itemId' => $bread->id, 'quantity' => null]],
    ])->assertJsonValidationErrors(['credits', 'items.0.quantity']);
});

it('gives a resident unlimited stock marked for sale', function () {
    [$user, , , $region, $resident] = worldStateScenario();
    $bread = worldItem($region);

    $this->actingAs($user)->putJson(route('worlds.starting-inventories.residents.update', [$region->world_id, $resident->id]), [
        'items' => [['itemId' => $bread->id, 'quantity' => null, 'forSale' => true]],
    ])->assertOk()->assertJsonPath('items.0.forSale', true);

    $this->getJson(route('worlds.starting-inventories.index', $region->world_id))
        ->assertJsonPath("residents.{$resident->id}.items.0.quantity", null);
});

it('keeps a resident\'s starting credits uncounted whatever is sent', function () {
    [$user, , , $region, $resident] = worldStateScenario();

    $this->actingAs($user)->putJson(route('worlds.starting-inventories.residents.update', [$region->world_id, $resident->id]), [
        'credits' => 500,
        'items' => [],
    ])->assertOk()->assertJsonPath('credits', null);

    expect(StartingInventory::sole()->credits)->toBeNull();
});

it('refuses stock for a resident whose model cannot call tools', function () {
    [$user, $assistant, , $region, $resident] = worldStateScenario();
    $assistant->update(['kind' => AssistantKind::WorldNpc]);
    AiModel::query()->update(['supports_tools' => false]);
    $bread = worldItem($region);

    $this->actingAs($user)->putJson(route('worlds.starting-inventories.residents.update', [$region->world_id, $resident->id]), [
        'items' => [['itemId' => $bread->id, 'quantity' => 1]],
    ])->assertJsonValidationErrors(['items' => "model can't call tools"]);

    $this->putJson(route('worlds.starting-inventories.residents.update', [$region->world_id, $resident->id]), [
        'items' => [],
    ])->assertOk();

    AiModel::query()->update(['supports_tools' => true]);
    Settings::query()->update(['data' => ['ai_model_id' => AiModel::first()->id]]);
    $this->putJson(route('worlds.starting-inventories.residents.update', [$region->world_id, $resident->id]), [
        'items' => [['itemId' => $bread->id, 'quantity' => 1]],
    ])->assertOk();
});
