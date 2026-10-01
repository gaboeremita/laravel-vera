<?php

use App\Actions\ResolveInventory;
use App\Models\ActivityTerms;
use App\Models\AiModel;
use App\Models\Assistant;
use App\Models\CreditTransaction;
use App\Models\InventoryItem;
use App\Models\Region;
use App\Models\ResidentActivity;
use App\Models\WorldResident;
use App\Models\WorldSessionObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function useLounger($test, array $scenario, ?string $attempt = null)
{
    [$user, , , $region, , $session] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.activity-uses.store', [$region->world_id, $session->id]), [
        'regionId' => $region->id, 'objectId' => 'pool-lounger-1', 'activityId' => 'recline', 'attempt' => $attempt,
    ]);
}

/**
 * @param  array<int, array{condition: ?array, effects: array<int, array<string, mixed>>}>  $responses
 */
function loungerTerms(array $scenario, array $responses, array $attributes = []): ActivityTerms
{
    $scenario[3]->world->update(['narrator_model_id' => AiModel::first()->id]);

    return ActivityTerms::factory()->create(['region_id' => $scenario[3]->id, 'object_id' => 'pool-lounger-1', 'activity_id' => 'recline', 'responses' => $responses, ...$attributes]);
}

function narrated(bool $succeeded, string $narration, string $action = 'tries the lounger'): array
{
    return toolCallResponse('call_'.$narration, 'narrate', ['succeeded' => $succeeded, 'narration' => $narration, 'action' => $action]);
}

/**
 * What the narrator was told on the call, by its position among the recorded requests.
 */
function narratorWasTold(int $call): string
{
    return collect(Http::recorded()[$call][0]['messages'])->firstWhere('role', 'user')['content'];
}

function loungerTermsUrl(Region $region, string $activity = 'recline'): string
{
    return route('worlds.regions.activity-terms.update', [$region->world_id, $region->id, 'pool-lounger-1', $activity]);
}

it('saves responses for an activity the object offers and nothing else', function () {
    [$user, , , $region] = worldStateScenario();
    $key = worldItem($region, ['name' => 'Iron key']);
    $responses = [
        ['condition' => ['has' => ['item' => $key->id, 'atLeast' => 1]], 'effects' => [['type' => 'takeCredits', 'amount' => 5, 'ignored' => true], ['type' => 'showText', 'text' => '  You doze off in the sun.  ']]],
        ['condition' => null, 'effects' => [['type' => 'showText', 'text' => 'It looks locked.']]],
    ];

    $this->actingAs($user)->putJson(loungerTermsUrl($region), ['responses' => $responses])
        ->assertOk()
        ->assertJsonPath('responses.0.effects', [['type' => 'takeCredits', 'amount' => 5], ['type' => 'showText', 'text' => 'You doze off in the sun.']])
        ->assertJsonPath('responses.1.condition', null);

    $this->getJson(route('worlds.regions.show', [$region->world_id, $region->id]))
        ->assertJsonPath("activityTerms.0.items.{$key->id}.name", 'Iron key');

    $this->putJson(loungerTermsUrl($region, 'dance'), ['responses' => []])->assertJsonValidationErrors('activity');

    $this->deleteJson(route('worlds.regions.activity-terms.destroy', [$region->world_id, $region->id, 'pool-lounger-1', 'recline']))->assertNoContent();
    expect(ActivityTerms::count())->toBe(0);
});

it('reports each problem with a response at the path it sits at', function () {
    [$user, , , $region] = worldStateScenario();
    $stranger = worldItem(Region::factory()->withLayout()->create());

    $this->actingAs($user)->putJson(loungerTermsUrl($region), ['responses' => [
        ['condition' => ['any' => [['narrator' => ['requirement' => 'Be polite.']], ['credits' => ['atLeast' => 1]]]], 'effects' => [['type' => 'fly']]],
        ['condition' => ['has' => ['item' => $stranger->id, 'atLeast' => 1]], 'effects' => [['type' => 'takeCredits', 'amount' => 0], ['type' => 'giveItems', 'items' => [['itemId' => $stranger->id, 'quantity' => 1]]], ['type' => 'showText', 'text' => ' ']]],
    ]])->assertJsonValidationErrors([
        'responses.0.condition.any.0',
        'responses.0.effects.0',
        'responses.1.condition',
        'responses.1.effects.0',
        'responses.1.effects.1',
        'responses.1.effects.2',
    ]);
});

it('names a vendor from the same world for an activity', function () {
    [$user, , , $region, $resident] = worldStateScenario();
    $stranger = WorldResident::factory()->create();

    $this->actingAs($user)->putJson(loungerTermsUrl($region), ['responses' => [], 'vendorResidentId' => $resident->id])->assertOk()->assertJsonPath('vendorResidentId', $resident->id);
    $this->putJson(loungerTermsUrl($region), ['responses' => [], 'vendorResidentId' => $stranger->id])->assertJsonValidationErrors('vendorResidentId');
});

it('lets an activity without responses simply happen', function () {
    $scenario = inventoryScenario();
    loungerTerms($scenario, []);

    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('narration', null);
});

it('has the narrator tell the player what they lack when the item or credits are missing', function () {
    $scenario = inventoryScenario(playerCredits: 3);
    [, , , $region] = $scenario;
    $key = worldItem($region, ['name' => 'Iron key']);
    loungerTerms($scenario, [['condition' => ['has' => ['item' => $key->id, 'atLeast' => 1]], 'effects' => [['type' => 'takeCredits', 'amount' => 5]]]]);
    fakeTurn(narrated(true, 'The lounger is chained shut.'), narrated(true, 'You pat your empty pockets.'));

    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('narration', 'The lounger is chained shut.');
    expect(narratorWasTold(0))->toContain('Requirement: it fails, because The player does not have the Iron key it needs.');

    InventoryItem::factory()->create(['inventory_id' => $scenario[6]->id, 'item_id' => $key->id, 'quantity' => 1]);
    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('narration', 'You pat your empty pockets.');
    expect(narratorWasTold(1))->toContain('It costs 5 credits and the player has only 3.');
});

it('narrates what the effects do, then charges credits, takes items and gives from the object\'s stock', function () {
    $scenario = inventoryScenario(playerCredits: 12);
    [, , , $region, , $session, $player] = $scenario;
    $coin = worldItem($region, ['name' => 'Coin']);
    $drink = worldItem($region, ['name' => 'Drink']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $coin->id, 'quantity' => 2]);
    $object = app(ResolveInventory::class)->forObject($session, $region, 'pool-lounger-1');
    InventoryItem::factory()->create(['inventory_id' => $object->id, 'item_id' => $drink->id, 'quantity' => 1]);
    loungerTerms($scenario, [['condition' => null, 'effects' => [
        ['type' => 'takeCredits', 'amount' => 5],
        ['type' => 'takeItems', 'items' => [['itemId' => $coin->id, 'quantity' => 1]]],
        ['type' => 'giveItems', 'items' => [['itemId' => $drink->id, 'quantity' => 1]]],
        ['type' => 'showText', 'text' => 'The machine hums and drops a can.'],
    ]]]);
    fakeTurn(narrated(false, 'A cold can thunks into the tray.'), narrated(true, 'The machine only rattles.'));

    useLounger($this, $scenario)
        ->assertOk()
        ->assertJsonPath('allowed', true)
        ->assertJsonPath('narration', 'A cold can thunks into the tray.')
        ->assertJsonPath('inventory.credits', 7)
        ->assertJsonPath('changes.credits', -5);
    expect(narratorWasTold(0))
        ->toContain('Requirement: none; it succeeds')
        ->toContain('The player pays 5 credits. The Pool lounger takes Coin from the player. The player receives Drink from the Pool lounger. The machine hums and drops a can.');

    expect($player->items()->where('item_id', $coin->id)->value('quantity'))->toBe(1)
        ->and($player->items()->where('item_id', $drink->id)->value('quantity'))->toBe(1)
        ->and($object->fresh()->credits)->toBe(5);

    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('narration', 'The machine only rattles.');
    expect(narratorWasTold(1))->toContain('The Pool lounger has run out of what it gives.');
});

it('pays credits from the object\'s stock', function () {
    $scenario = inventoryScenario(playerCredits: 0);
    [, , , $region, , $session] = $scenario;
    app(ResolveInventory::class)->forObject($session, $region, 'pool-lounger-1')->update(['credits' => 10]);
    loungerTerms($scenario, [['condition' => null, 'effects' => [['type' => 'giveCredits', 'amount' => 10]]]]);
    fakeTurn(narrated(true, 'Coins spill out.'), narrated(true, 'Nothing comes out.'));

    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('inventory.credits', 10);
    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', false);
    expect(narratorWasTold(1))->toContain('The Pool lounger has run out of credits to give.');
});

it('runs the first response whose condition is met, and opens the way for the rest of the session', function () {
    $scenario = inventoryScenario();
    [$user, , , $region, , $session, $player] = $scenario;
    $key = worldItem($region, ['name' => 'Laundry key']);
    loungerTerms($scenario, [
        ['condition' => ['objectState' => 'passable'], 'effects' => [['type' => 'showText', 'text' => 'The door stands open.']]],
        ['condition' => ['has' => ['item' => $key->id, 'atLeast' => 1]], 'effects' => [['type' => 'showText', 'text' => 'The key turns.'], ['type' => 'makePassable']]],
        ['condition' => null, 'effects' => [['type' => 'showText', 'text' => 'It is locked.']]],
    ]);
    fakeTurn(narrated(true, 'The handle will not give.'), narrated(true, 'The lock clicks and the door swings open.'), narrated(true, 'You walk through the open door.'));

    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('narration', 'The handle will not give.')->assertJsonPath('objectState', null);
    expect(narratorWasTold(0))->toContain('It is locked.')
        ->and(WorldSessionObject::count())->toBe(0);

    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $key->id, 'quantity' => 1]);
    useLounger($this, $scenario)
        ->assertOk()
        ->assertJsonPath('narration', 'The lock clicks and the door swings open.')
        ->assertJsonPath('objectState', ['regionId' => $region->id, 'objectId' => 'pool-lounger-1', 'state' => ['passable' => true]]);
    expect(narratorWasTold(1))->toContain('The key turns. The Pool lounger opens the way: from now on the player can pass through it.');

    useLounger($this, $scenario)->assertOk();
    expect(narratorWasTold(2))->toContain('The door stands open.');

    $this->actingAs($user)->getJson(route('worlds.sessions.index', $region->world_id))
        ->assertJsonPath('0.objectStates', [['regionId' => $region->id, 'objectId' => 'pool-lounger-1', 'state' => ['passable' => true]]]);
    expect(WorldSessionObject::sole()->world_session_id)->toBe($session->id);
});

it('has the narrator tell the player nothing works when no response is met', function () {
    $scenario = inventoryScenario();
    loungerTerms($scenario, [['condition' => ['objectState' => 'passable'], 'effects' => []]]);
    fakeTurn(narrated(true, 'You push, but nothing gives.'));

    useLounger($this, $scenario)->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('narration', 'You push, but nothing gives.');
    expect(narratorWasTold(0))->toContain('Requirement: it fails, because nothing the player can do here works right now.');
});

it('judges a plain-language requirement and narrates it in one call, only once the effects can apply', function () {
    $scenario = inventoryScenario(playerCredits: 0);
    [, , , , , , $player] = $scenario;
    loungerTerms($scenario, [['condition' => ['narrator' => ['requirement' => 'Only for guild members who show proof.', 'outcome' => 'The attendant waves you through.']], 'effects' => [['type' => 'takeCredits', 'amount' => 2]]]]);
    fakeTurn(
        narrated(true, 'You have no money for the attendant.'),
        narrated(false, 'The attendant shakes their head.', 'smiles at the attendant'),
        narrated(true, 'The attendant waves you through.', 'flashes a signet at the attendant'),
    );

    useLounger($this, $scenario, 'I smile')->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('narration', 'You have no money for the attendant.');
    expect(narratorWasTold(0))->not->toContain('Only for guild members');

    $player->update(['credits' => 10]);
    useLounger($this, $scenario, 'I smile')->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('narration', 'The attendant shakes their head.');
    expect(narratorWasTold(1))
        ->toContain('Requirement: Only for guild members who show proof.')
        ->toContain('Outcome when it succeeds: The attendant waves you through. The player pays 2 credits.')
        ->toContain('Outcome when it fails: nothing happens')
        ->toContain('What they do or say: I smile');

    useLounger($this, $scenario, 'I show the signet')->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('action', 'flashes a signet at the attendant');
    expect($player->fresh()->credits)->toBe(8)
        ->and(Http::recorded())->toHaveCount(3);
});

it('runs the next response the code can check when the narrator turns the attempt down', function () {
    $scenario = inventoryScenario();
    [, , , , , , $player] = $scenario;
    loungerTerms($scenario, [
        ['condition' => ['narrator' => ['requirement' => 'Talk your way in.', 'outcome' => '']], 'effects' => [['type' => 'makePassable']]],
        ['condition' => ['narrator' => ['requirement' => 'Bribe the guard.', 'outcome' => '']], 'effects' => [['type' => 'takeCredits', 'amount' => 50]]],
        ['condition' => null, 'effects' => [['type' => 'takeCredits', 'amount' => 1], ['type' => 'showText', 'text' => 'The guard fines you for trying.']]],
    ]);
    fakeTurn(narrated(false, 'The guard laughs and fines you a credit.'));

    useLounger($this, $scenario, 'Let me in')->assertOk()->assertJsonPath('allowed', true)->assertJsonPath('narration', 'The guard laughs and fines you a credit.');

    expect(narratorWasTold(0))->toContain('Outcome when it fails: The player pays 1 credits. The guard fines you for trying.')
        ->and($player->fresh()->credits)->toBe(99)
        ->and(WorldSessionObject::count())->toBe(0);
});

it('moves the old terms columns into one response', function () {
    $scenario = inventoryScenario();
    [, , , $region, $resident] = $scenario;
    $key = worldItem($region);
    $drink = worldItem($region);
    $fact = worldFact($resident);
    $migration = require database_path('migrations/2026_09_29_221039_move_activity_terms_into_responses.php');
    $migration->down();
    $row = fn (array $columns) => DB::table('activity_terms')->insertGetId([
        'region_id' => $region->id, 'object_id' => 'pool-lounger-1', 'created_at' => now(), 'updated_at' => now(), ...$columns,
    ]);
    $full = $row(['activity_id' => 'recline', 'required_item_id' => $key->id, 'consumes_required' => true, 'cost' => 5, 'gives_credits' => 2, 'gives_items' => json_encode([['itemId' => $drink->id, 'quantity' => 1]]), 'requirement' => 'Be a guest.', 'outcome' => 'You relax.', 'reveals_fact_id' => $fact->id, 'vendor_resident_id' => $resident->id]);
    $keyOnly = $row(['activity_id' => 'sit', 'required_item_id' => $key->id]);
    $empty = $row(['activity_id' => 'lie', 'vendor_resident_id' => $resident->id]);

    $migration->up();

    expect(ActivityTerms::find($full)->responses)->toBe([[
        'condition' => ['all' => [['has' => ['item' => $key->id, 'atLeast' => 1]], ['narrator' => ['requirement' => 'Be a guest.', 'outcome' => 'You relax.']]]],
        'effects' => [
            ['type' => 'takeCredits', 'amount' => 5],
            ['type' => 'takeItems', 'items' => [['itemId' => $key->id, 'quantity' => 1]]],
            ['type' => 'giveCredits', 'amount' => 2],
            ['type' => 'giveItems', 'items' => [['itemId' => $drink->id, 'quantity' => 1]]],
            ['type' => 'revealFact', 'fact' => $fact->id],
        ],
    ]])
        ->and(ActivityTerms::find($full)->vendor_resident_id)->toBe($resident->id)
        ->and(ActivityTerms::find($keyOnly)->responses)->toBe([['condition' => ['has' => ['item' => $key->id, 'atLeast' => 1]], 'effects' => []]])
        ->and(ActivityTerms::find($empty)->responses)->toBe([]);
});

it('lets a resident use a paid activity without charging them', function () {
    $scenario = autonomousTermsScenario();
    [, , , , $resident, $session] = $scenario;
    $residentInventory = app(ResolveInventory::class)->forResident($session, $resident);
    loungerTerms($scenario, [['condition' => null, 'effects' => [['type' => 'takeCredits', 'amount' => 5]]]]);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('(Sun) *stretches out*'));

    requestTermsDecision($this, $scenario)->assertCreated();

    expect($residentInventory->fresh()->credits)->toBeNull()
        ->and(CreditTransaction::count())->toBe(0)
        ->and(ResidentActivity::where('verb', 'use')->count())->toBe(1);
});

it('keeps an activity\'s credits in the object when a resident uses it', function () {
    $scenario = autonomousTermsScenario();
    [, , , $region, $resident, $session] = $scenario;
    $object = app(ResolveInventory::class)->forObject($session, $region, 'pool-lounger-1');
    $object->update(['credits' => 20]);
    loungerTerms($scenario, [['condition' => null, 'effects' => [['type' => 'giveCredits', 'amount' => 10]]]]);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('(Sun) *stretches out*'));

    requestTermsDecision($this, $scenario)->assertCreated();

    expect($object->fresh()->credits)->toBe(20)
        ->and(app(ResolveInventory::class)->forResident($session, $resident)->credits)->toBeNull()
        ->and(CreditTransaction::count())->toBe(0)
        ->and(ResidentActivity::where('verb', 'use')->count())->toBe(1);
});

it('leaves responses to the player, so a resident uses an activity whose responses they would not meet', function () {
    $scenario = autonomousTermsScenario();
    loungerTerms($scenario, [['condition' => ['has' => ['item' => worldItem($scenario[3], ['name' => 'Towel'])->id, 'atLeast' => 1]], 'effects' => [['type' => 'makePassable']]]]);
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('(Sun) *stretches out*'));

    requestTermsDecision($this, $scenario)->assertCreated();

    expect(ResidentActivity::where('verb', 'use')->count())->toBe(1)
        ->and(WorldSessionObject::count())->toBe(0);
});

it('leaves an activity to its vendor while the vendor is in the room, and lets a resident use it otherwise', function () {
    $scenario = autonomousTermsScenario();
    $vendor = vendorResident($scenario[3]);
    loungerTerms($scenario, [], ['vendor_resident_id' => $vendor->id]);
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
    loungerTerms($scenario, [], ['vendor_resident_id' => $vendor->id]);
    $vendorInventory = app(ResolveInventory::class)->forResident($session, $vendor);
    InventoryItem::factory()->forSale()->create(['inventory_id' => $vendorInventory->id, 'item_id' => worldItem($region, ['name' => 'Tacos'])->id]);
    InventoryItem::factory()->create(['inventory_id' => $vendorInventory->id, 'item_id' => worldItem($region, ['name' => 'Secret salsa'])->id]);
    fakeTurn(finalAnswerResponse('(I feel like tacos) *heads for the tacos*'));

    requestTermsDecision($this, $scenario, [$vendor->id => ['x' => 6, 'y' => 0, 'z' => -3]])->assertCreated();

    expect(sentPrompt())
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
