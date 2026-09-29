<?php

use App\Actions\ResolveInventory;
use App\Models\ActivityTerms;
use App\Models\AiModel;
use App\Models\Assistant;
use App\Models\InventoryItem;
use App\Models\Region;
use App\Models\ResidentActivity;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function useLounger($test, array $scenario, ?string $attempt = null)
{
    [$user, , , $region, , $session] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.activity-uses.store', [$region->world_id, $session->id]), [
        'regionId' => $region->id, 'objectId' => 'pool-lounger-1', 'activityId' => 'recline', 'attempt' => $attempt,
    ]);
}

function loungerTerms(array $scenario, array $attributes): ActivityTerms
{
    return ActivityTerms::factory()->create(['region_id' => $scenario[3]->id, 'object_id' => 'pool-lounger-1', 'activity_id' => 'recline', ...$attributes]);
}

it('saves terms for an activity the object offers and nothing else', function () {
    [$user, , , $region] = worldStateScenario();
    $key = worldItem($region, ['name' => 'Iron key']);

    $this->actingAs($user)->putJson(route('worlds.regions.activity-terms.update', [$region->world_id, $region->id, 'pool-lounger-1', 'recline']), [
        'requiredItemId' => $key->id, 'consumesRequired' => false, 'cost' => 5, 'givesCredits' => 0, 'givesItems' => [], 'requirement' => null, 'outcome' => 'You doze off in the sun.',
    ])->assertOk()->assertJsonPath('cost', 5)->assertJsonPath('requiredItemId', $key->id);

    $this->getJson(route('worlds.regions.show', [$region->world_id, $region->id]))
        ->assertJsonPath('activityTerms.0.requiredItemName', 'Iron key');

    $this->putJson(route('worlds.regions.activity-terms.update', [$region->world_id, $region->id, 'pool-lounger-1', 'dance']), ['cost' => 1])
        ->assertJsonValidationErrors('activity');

    $this->deleteJson(route('worlds.regions.activity-terms.destroy', [$region->world_id, $region->id, 'pool-lounger-1', 'recline']))->assertNoContent();
    expect(ActivityTerms::count())->toBe(0);
});

it('names a vendor from the same world for an activity', function () {
    [$user, , , $region, $resident] = worldStateScenario();
    $stranger = WorldResident::factory()->create();
    $url = route('worlds.regions.activity-terms.update', [$region->world_id, $region->id, 'pool-lounger-1', 'recline']);

    $this->actingAs($user)->putJson($url, ['cost' => 4, 'vendorResidentId' => $resident->id])->assertOk()->assertJsonPath('vendorResidentId', $resident->id);
    $this->putJson($url, ['cost' => 4, 'vendorResidentId' => $stranger->id])->assertJsonValidationErrors('vendorResidentId');
});

it('refuses without the required item or enough credits, naming what is missing', function () {
    $scenario = inventoryScenario(playerCredits: 3);
    [, , , $region] = $scenario;
    $key = worldItem($region, ['name' => 'Iron key']);
    loungerTerms($scenario, ['required_item_id' => $key->id, 'cost' => 5]);

    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('reason', 'Needs the Iron key.');

    InventoryItem::factory()->create(['inventory_id' => $scenario[6]->id, 'item_id' => $key->id, 'quantity' => 1]);
    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('reason', 'Costs 5 credits.');
});

it('charges the cost, keeps or consumes the item and gives from the object\'s stock', function () {
    $scenario = inventoryScenario(playerCredits: 12);
    [, , , $region, , $session, $player] = $scenario;
    $coin = worldItem($region, ['name' => 'Coin']);
    $drink = worldItem($region, ['name' => 'Drink']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $coin->id, 'quantity' => 2]);
    $object = app(ResolveInventory::class)->forObject($session, $region, 'pool-lounger-1');
    InventoryItem::factory()->create(['inventory_id' => $object->id, 'item_id' => $drink->id, 'quantity' => 1]);
    loungerTerms($scenario, ['required_item_id' => $coin->id, 'consumes_required' => true, 'cost' => 5, 'gives_items' => [['itemId' => $drink->id, 'quantity' => 1]]]);

    useLounger($this, $scenario)
        ->assertOk()
        ->assertJsonPath('allowed', true)
        ->assertJsonPath('inventory.credits', 7)
        ->assertJsonPath('changes.credits', -5);

    expect($player->items()->where('item_id', $coin->id)->value('quantity'))->toBe(1)
        ->and($player->items()->where('item_id', $drink->id)->value('quantity'))->toBe(1)
        ->and($object->fresh()->credits)->toBe(5);

    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('reason', 'There is nothing left to get here.');
});

it('asks the narrator about a plain-language requirement', function () {
    $scenario = inventoryScenario();
    [, , , $region] = $scenario;
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    loungerTerms($scenario, ['requirement' => 'Only for guild members who show proof.', 'outcome' => 'The attendant waves you through.']);

    fakeTurn(
        toolCallResponse('call_1', 'narrate', ['succeeded' => false, 'narration' => 'The attendant shakes their head.', 'action' => 'smiles at the attendant']),
        toolCallResponse('call_2', 'narrate', ['succeeded' => true, 'narration' => 'The attendant waves you through.', 'action' => 'flashes a signet at the attendant']),
    );
    useLounger($this, $scenario, 'I smile')->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('narration', 'The attendant shakes their head.');
    expect(collect(Http::recorded()[0][0]['messages'])->firstWhere('role', 'user')['content'])->toContain('What they do or say: I smile');

    useLounger($this, $scenario, 'I show the signet')->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('narration', 'The attendant waves you through.')->assertJsonPath('action', 'flashes a signet at the attendant');
});

it('lets a resident use a paid activity without charging them', function () {
    $scenario = autonomousTermsScenario();
    [, , , , $resident, $session] = $scenario;
    $residentInventory = app(ResolveInventory::class)->forResident($session, $resident);
    $residentInventory->update(['credits' => 0]);
    loungerTerms($scenario, ['cost' => 5]);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('(Sun) *stretches out*'));

    requestTermsDecision($this, $scenario)->assertCreated();

    expect($residentInventory->fresh()->credits)->toBe(0)
        ->and(ResidentActivity::where('verb', 'use')->count())->toBe(1);
});

it('leaves activities that need an item a resident lacks out of their choices', function () {
    $scenario = autonomousTermsScenario();
    loungerTerms($scenario, ['required_item_id' => worldItem($scenario[3], ['name' => 'Towel'])->id]);
    fakeTurn(finalAnswerResponse('(Nothing to do) *waits*'));

    requestTermsDecision($this, $scenario)->assertCreated();

    expect(collect(Http::recorded()[0][0]['tools'])->pluck('function.name')->all())->not->toContain('use');
});

it('leaves an activity to its vendor while the vendor is in the room, and lets a resident use it otherwise', function () {
    $scenario = autonomousTermsScenario();
    $vendor = vendorResident($scenario[3]);
    loungerTerms($scenario, ['vendor_resident_id' => $vendor->id]);
    fakeTurn(finalAnswerResponse('(Nothing to do) *waits*'), toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('(Sun) *stretches out*'));

    requestTermsDecision($this, $scenario, [$vendor->id => ['x' => 6, 'y' => 0, 'z' => -3]])->assertCreated();
    expect(collect(Http::recorded()[0][0]['tools'])->pluck('function.name')->all())->not->toContain('use');

    $this->travel(10)->seconds();
    requestTermsDecision($this, $scenario)->assertCreated();

    expect(ResidentActivity::where('verb', 'use')->count())->toBe(1);
});

it('tells a resident who sells what in their region, and nothing that is not for sale', function () {
    $scenario = autonomousTermsScenario();
    [, , , $region, , $session] = $scenario;
    $vendor = vendorResident($region);
    loungerTerms($scenario, ['vendor_resident_id' => $vendor->id]);
    $vendorInventory = app(ResolveInventory::class)->forResident($session, $vendor);
    InventoryItem::factory()->forSale()->create(['inventory_id' => $vendorInventory->id, 'item_id' => worldItem($region, ['name' => 'Tacos'])->id]);
    InventoryItem::factory()->create(['inventory_id' => $vendorInventory->id, 'item_id' => worldItem($region, ['name' => 'Secret salsa'])->id]);
    fakeTurn(finalAnswerResponse('(I feel like tacos) *heads for the tacos*'));

    requestTermsDecision($this, $scenario, [$vendor->id => ['x' => 6, 'y' => 0, 'z' => -3]])->assertCreated();

    expect(sentSystemPrompt())
        ->toContain('Rosa (serves at the Pool lounger, now in Pool terrace): Tacos')
        ->toContain('It costs you nothing')
        ->not->toContain('Secret salsa');
});

function vendorResident(Region $region): WorldResident
{
    return $region->residents()->create(['assistant_id' => Assistant::factory()->create(['name' => 'Rosa'])->id, 'position' => ['x' => 6, 'y' => 0, 'z' => -3], 'behavior' => 'stationary']);
}

function autonomousTermsScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    $scenario[4]->update(['behavior' => 'autonomous']);

    return $scenario;
}

/**
 * @param  array<int, array{x: float, y: float, z: float}>  $otherResidents  where other residents are, by resident id
 */
function requestTermsDecision($test, array $scenario, array $otherResidents = [])
{
    [$user, , , $region, $resident, $session] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.residents.decisions.store', [$region->world_id, $session->id, $resident->id]), [
        'positions' => ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]] + $otherResidents],
    ]);
}
