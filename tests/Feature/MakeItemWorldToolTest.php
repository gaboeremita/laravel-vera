<?php

use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function makerScenario(array $makesItemsAt = ['recline']): array
{
    $scenario = inventoryScenario();
    $scenario[4]->update(['behavior_settings' => ['makesItemsAt' => $makesItemsAt]]);

    return $scenario;
}

function makerPositions(array $scenario): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
}

function fakeMakingTurn(array ...$llmResponses): void
{
    $sequence = Http::sequence();
    foreach ($llmResponses as $response) {
        $sequence->push($response);
    }
    Http::fake(['fake-llm.test/*' => $sequence, 'openrouter.ai/*' => Http::response(imageGenHttpResponse())]);
}

it('lets a resident make an item with a generated picture and give it to the player', function () {
    $scenario = makerScenario();
    [, , , , $resident, $session, $player, $residentInventory] = $scenario;
    fakeMakingTurn(
        toolCallResponse('call_1', 'make_item', ['name' => 'Harbour at Dusk', 'description' => 'A small oil painting of the harbour at dusk.', 'picture' => 'an oil painting of a harbour at dusk']),
        finalAnswerResponse('a detailed oil painting of a harbour at dusk'),
        toolCallResponse('call_2', 'give', ['items' => [['item' => 'Harbour at Dusk', 'quantity' => 1]]]),
        finalAnswerResponse('This one is for you.'),
    );

    sendWorldMessage($this, $scenario, makerPositions($scenario))
        ->assertOk()
        ->assertJsonPath('changes.items.0.delta', 1);

    $painting = Item::sole();
    expect($painting)
        ->world_session_id->toBe($session->id)
        ->made_by_resident_id->toBe($resident->id)
        ->description->toBe('A small oil painting of the harbour at dusk.')
        ->and($painting->cardImage)->not->toBeNull()
        ->and($player->items()->sole()->item_id)->toBe($painting->id)
        ->and($residentInventory->items()->count())->toBe(0);
    Storage::disk('public')->assertExists($painting->cardImage->path);
});

it('offers make_item only to residents who make items', function (array $makesItemsAt, bool $offered) {
    $scenario = makerScenario($makesItemsAt);
    fakeTurn(finalAnswerResponse('Hello.'));

    sendWorldMessage($this, $scenario, makerPositions($scenario))->assertOk();

    expect(in_array('make_item', offeredToolNames(), true))->toBe($offered);
})->with([
    'makes items' => [['recline'], true],
    'makes none' => [[], false],
]);

it('refuses a name another item in the world already has', function () {
    $scenario = makerScenario();
    worldItem($scenario[3], ['name' => 'Bread']);
    fakeMakingTurn(
        toolCallResponse('call_1', 'make_item', ['name' => 'bread', 'description' => 'A sketch of bread.', 'picture' => 'a pencil sketch of bread']),
        finalAnswerResponse('Never mind.'),
    );

    sendWorldMessage($this, $scenario, makerPositions($scenario))->assertOk();

    expect(toolResultSentBack())->toContain('already exists in this world; choose another name')
        ->and(Item::count())->toBe(1);
});

it('lists what the resident has made so far in the tool', function () {
    $scenario = makerScenario();
    [, , , $region, $resident, $session] = $scenario;
    worldItem($region, ['name' => 'Harbour at Dusk', 'world_session_id' => $session->id, 'made_by_resident_id' => $resident->id]);
    fakeTurn(finalAnswerResponse('Hello.'));

    sendWorldMessage($this, $scenario, makerPositions($scenario))->assertOk();

    $tool = collect(Http::recorded()->first()[0]['tools'])->firstWhere('function.name', 'make_item');
    expect($tool['function']['description'])->toContain('What you have made so far: Harbour at Dusk.');
});

it('offers make_item while idle only during an item-making activity', function (?array $residentState, bool $offered) {
    $scenario = makerScenario();
    [$user, , , $region, $resident, $session] = $scenario;
    $resident->update(['behavior' => 'autonomous']);
    fakeTurn(finalAnswerResponse('(Quiet) *stays put*'));

    $this->actingAs($user)->postJson(route('worlds.sessions.residents.decisions.store', [$region->world_id, $session->id, $resident->id]), [
        'positions' => makerPositions($scenario),
        ...($residentState !== null ? ['residentState' => $residentState] : []),
    ])->assertCreated();

    expect(in_array('make_item', offeredToolNames(), true))->toBe($offered);
})->with([
    'at the activity' => [['posture' => 'reclining', 'spotId' => 'pool-lounger-1-seat', 'activityId' => 'recline'], true],
    'standing around' => [null, false],
]);

it('keeps made items out of the world item editor and out of other sessions', function () {
    [$user, , , $region, $resident, $session] = makerScenario();
    worldItem($region, ['name' => 'Bread']);
    worldItem($region, ['name' => 'Harbour at Dusk', 'world_session_id' => $session->id, 'made_by_resident_id' => $resident->id]);
    $otherSession = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $region->id]);

    $this->actingAs($user)->getJson(route('worlds.items.index', $region->world_id))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Bread');

    expect(Item::availableIn($session)->pluck('name')->all())->toContain('Harbour at Dusk')
        ->and(Item::availableIn($otherSession)->pluck('name')->all())->toBe(['Bread']);
});

it('deletes the items made in a session, with their pictures, along with it', function () {
    [$user, , , $region, $resident, $session, $player] = makerScenario();
    $painting = worldItem($region, ['name' => 'Harbour at Dusk', 'world_session_id' => $session->id, 'made_by_resident_id' => $resident->id]);
    $painting->cardImage()->create(['path' => 'worlds/1/items/1/card/x.png', 'disk' => 'public', 'mime_type' => 'image/png', 'size' => 3, 'role' => 'card']);
    Storage::disk('public')->put('worlds/1/items/1/card/x.png', 'png');
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $painting->id, 'quantity' => 1]);

    $this->actingAs($user)->deleteJson(route('worlds.sessions.destroy', [$region->world_id, $session->id]))->assertNoContent();

    expect(Item::find($painting->id))->toBeNull();
    Storage::disk('public')->assertMissing('worlds/1/items/1/card/x.png');
});
