<?php

use App\Models\AiModel;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function narratedScenario(): array
{
    $scenario = inventoryScenario(playerCredits: 0);
    $scenario[3]->world->update(['narrator_model_id' => AiModel::first()->id]);

    return $scenario;
}

it('narrates what examining an item reveals', function () {
    $scenario = narratedScenario();
    [$user, , , $region, , $session, $player] = $scenario;
    $letter = worldItem($region, ['name' => 'Letter', 'contents' => 'Signed only "M", asking to meet at the old pier.']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $letter->id]);
    fakeTurn(toolCallResponse('call_1', 'narrate', ['succeeded' => true, 'narration' => 'The letter asks you to come to the old pier.']));

    $this->actingAs($user)->getJson(route('worlds.sessions.items.examine', [$region->world_id, $session->id, $letter->id]))
        ->assertOk()
        ->assertJsonPath('narration', 'The letter asks you to come to the old pier.');
    expect(collect(Http::recorded()[0][0]['messages'])->firstWhere('role', 'user')['content'])->toContain('asking to meet at the old pier');
});

it('releases and consumes an item only when the narrator judges the attempt right', function () {
    $scenario = narratedScenario();
    [$user, , , $region, , $session, $player] = $scenario;
    $ring = worldItem($region, ['name' => 'Ring']);
    $lockbox = worldItem($region, ['name' => 'Lockbox', 'use_requirement' => 'Opens with the code 4471.', 'consumed_on_use' => true, 'releases_credits' => 40, 'releases_items' => [['itemId' => $ring->id, 'quantity' => 1]]]);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $lockbox->id]);

    fakeTurn(toolCallResponse('call_1', 'narrate', ['succeeded' => false, 'narration' => 'The dial clicks back to zero.']));
    $this->actingAs($user)->postJson(route('worlds.sessions.items.use', [$region->world_id, $session->id, $lockbox->id]), ['attempt' => 'I enter 1234'])
        ->assertOk()->assertJsonPath('succeeded', false)->assertJsonPath('inventory.credits', 0);

    fakeTurn(toolCallResponse('call_1', 'narrate', ['succeeded' => true, 'narration' => 'The lid springs open.']));
    $this->postJson(route('worlds.sessions.items.use', [$region->world_id, $session->id, $lockbox->id]), ['attempt' => 'I enter 4471'])
        ->assertOk()
        ->assertJsonPath('succeeded', true)
        ->assertJsonPath('inventory.credits', 40)
        ->assertJsonPath('inventory.items.0.name', 'Ring');

    expect($player->items()->where('item_id', $lockbox->id)->exists())->toBeFalse();
});

it('refuses items the player does not hold', function () {
    [$user, , , $region, , $session] = narratedScenario();
    $letter = worldItem($region, ['contents' => 'Secret.']);

    $this->actingAs($user)->getJson(route('worlds.sessions.items.examine', [$region->world_id, $session->id, $letter->id]))->assertUnprocessable();
    $this->postJson(route('worlds.sessions.items.use', [$region->world_id, $session->id, $letter->id]))->assertUnprocessable();
});
