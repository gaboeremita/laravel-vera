<?php

use App\Actions\ResolveInventory;
use App\Enums\HandoverRequestStatus;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\InventoryItem;
use App\Models\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function chatPositions(array $scenario): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
}

it('lets a resident hand the player credits and items from their own inventory', function () {
    $scenario = inventoryScenario(playerCredits: 10);
    [, , , $region, , , $player, $resident] = $scenario;
    $bread = worldItem($region, ['name' => 'Bread']);
    InventoryItem::factory()->create(['inventory_id' => $resident->id, 'item_id' => $bread->id, 'quantity' => 3]);
    fakeTurn(toolCallResponse('call_1', 'give', ['credits' => 20, 'items' => [['item' => 'Bread', 'quantity' => 2]]]), finalAnswerResponse('Take these.'));

    sendWorldMessage($this, $scenario, chatPositions($scenario))
        ->assertOk()
        ->assertJsonPath('inventory.credits', 30)
        ->assertJsonPath('changes.credits', 20)
        ->assertJsonPath('changes.items.0.delta', 2);

    expect($resident->fresh()->credits)->toBeNull()
        ->and($resident->items()->first()->quantity)->toBe(1)
        ->and($player->items()->first()->quantity)->toBe(2);
});

it('refuses a hand-over beyond what the resident carries and tells them why', function () {
    $scenario = inventoryScenario(playerCredits: 10);
    [, $assistant, , $region, , , $player, $resident] = $scenario;
    InventoryItem::factory()->create(['inventory_id' => $resident->id, 'item_id' => worldItem($region, ['name' => 'Bread'])->id, 'quantity' => 1]);
    fakeTurn(toolCallResponse('call_1', 'give', ['items' => [['item' => 'Bread', 'quantity' => 3]]]), finalAnswerResponse('Ah, I am short.'));

    sendWorldMessage($this, $scenario, chatPositions($scenario))->assertOk()->assertJsonPath('inventory.credits', 10);

    expect(toolResultSentBack())->toContain("{$assistant->name} has only 1 Bread")
        ->and($resident->items()->first()->quantity)->toBe(1)
        ->and($player->items()->count())->toBe(0);
});

it('lets a resident give any amount of credits without ever running out', function () {
    $scenario = inventoryScenario(playerCredits: 0);
    [, , , , , , $player, $resident] = $scenario;
    fakeTurn(
        toolCallResponse('call_1', 'give', ['credits' => 1000]),
        toolCallResponse('call_2', 'give', ['credits' => 1000]),
        finalAnswerResponse('A fortune.'),
    );

    sendWorldMessage($this, $scenario, chatPositions($scenario))->assertOk();

    expect($resident->fresh()->credits)->toBeNull()->and($player->fresh()->credits)->toBe(2000);
});

it('never takes from the player through the resident\'s tools', function () {
    $scenario = inventoryScenario(playerCredits: 100);
    [, , , $region, , , $player] = $scenario;
    $key = worldItem($region, ['name' => 'Iron key']);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $key->id, 'quantity' => 1]);
    fakeTurn(
        toolCallResponse('call_1', 'give', ['credits' => 100, 'items' => [['item' => 'Iron key', 'quantity' => 1]]]),
        toolCallResponse('call_2', 'ask_for', ['credits' => 100, 'items' => [['item' => 'Iron key', 'quantity' => 1]], 'reason' => 'for safekeeping']),
        finalAnswerResponse('Hand them over.'),
    );

    sendWorldMessage($this, $scenario, chatPositions($scenario))
        ->assertOk()
        ->assertJsonPath('inventory.credits', 100)
        ->assertJsonPath('handoverRequest.reason', 'for safekeeping')
        ->assertJsonPath('handoverRequest.affordable', true);

    expect($player->fresh()->credits)->toBe(100)
        ->and($player->items()->first()->quantity)->toBe(1)
        ->and(HandoverRequest::sole()->status)->toBe(HandoverRequestStatus::Pending);
});

it('lets a resident check without asking whether the player carries an item', function (int $quantity, bool $holds) {
    $scenario = inventoryScenario();
    [, , , $region, , , $player] = $scenario;
    $chit = worldItem($region, ['name' => 'Fork toll chit']);
    if ($quantity > 0) {
        InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $chit->id, 'quantity' => $quantity]);
    }
    fakeTurn(toolCallResponse('call_1', 'check_holds', ['item' => 'fork toll chit']), finalAnswerResponse('Go on through.'));

    sendWorldMessage($this, $scenario, chatPositions($scenario))->assertOk();

    expect(collect(Http::recorded()[1][0]['messages'])->firstWhere('role', 'tool')['content'])
        ->toContain('"holds":'.($holds ? 'true' : 'false'))
        ->toContain('"quantity":'.$quantity)
        ->and($player->items()->where('item_id', $chit->id)->value('quantity'))->toBe($quantity > 0 ? $quantity : null)
        ->and(HandoverRequest::count())->toBe(0);
})->with([
    'holding it' => [2, true],
    'without it' => [0, false],
]);

it('tells a resident checking for an item the world does not have', function () {
    $scenario = inventoryScenario();
    fakeTurn(toolCallResponse('call_1', 'check_holds', ['item' => 'Golden ticket']), finalAnswerResponse('Never mind.'));

    sendWorldMessage($this, $scenario, chatPositions($scenario))->assertOk();

    expect(collect(Http::recorded()[1][0]['messages'])->firstWhere('role', 'tool')['content'])->toContain('There is no item called \"Golden ticket\" in this world.');
});

it('tells a resident what they carry and never what the player carries', function () {
    $scenario = inventoryScenario(playerCredits: 777);
    [, , , $region, , , $player, $resident] = $scenario;
    InventoryItem::factory()->forSale()->create(['inventory_id' => $resident->id, 'item_id' => worldItem($region, ['name' => 'Bread', 'base_price' => 2])->id, 'quantity' => 4]);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => worldItem($region, ['name' => 'Secret map'])->id]);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello.'))]);

    sendWorldMessage($this, $scenario, chatPositions($scenario))->assertOk();

    expect(sentPrompt())
        ->toContain('You can pay or give the user any amount of credits the moment calls for.')
        ->toContain('4 Bread (for sale, usually 2 credits for one Bread)')
        ->not->toContain('Secret map')
        ->not->toContain('777');
});

it('lets residents hand each other items for free while they talk', function () {
    [$user, $yinlin, , $region, $first, $session] = worldStateScenario(fakeReply: false);
    $vera = Assistant::factory()->create(['name' => 'Vera', 'mode' => 'agent']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $vera->id]);
    Settings::create(['user_id' => $user->id, 'assistant_id' => $vera->id, 'data' => Settings::where('user_id', $user->id)->where('assistant_id', $yinlin->id)->first()->data]);
    $second = $region->residents()->create(['assistant_id' => $vera->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'autonomous']);
    $veraInventory = app(ResolveInventory::class)->forResident($session, $second);
    InventoryItem::factory()->forSale()->create(['inventory_id' => $veraInventory->id, 'item_id' => worldItem($region, ['name' => 'Tacos', 'base_price' => 15])->id, 'quantity' => 3]);
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera)->forWorldSession($session)->create(['resumed_at' => now()->subMinute()]);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Two tacos, please!', 'speaker_type' => $yinlin->getMorphClass(), 'speaker_id' => $yinlin->id])->forceFill(['created_at' => now()->subMinute()])->save();
    fakeTurn(toolCallResponse('call_1', 'give', ['credits' => 10, 'items' => [['item' => 'Tacos', 'quantity' => 2]]]), finalAnswerResponse('Here you go.'));

    $this->actingAs($user)->postJson(route('worlds.sessions.conversations.turns.store', [$region->world_id, $session->id, $conversation->id]), [
        'positions' => ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$first->id => ['x' => 5, 'y' => 0, 'z' => -3], $second->id => ['x' => 6, 'y' => 0, 'z' => -3]]],
    ])->assertSuccessful();

    $giveTool = collect(Http::recorded()[0][0]['tools'])->firstWhere('function.name', 'give');
    $yinlinInventory = app(ResolveInventory::class)->forResident($session, $first);
    expect($giveTool['function']['parameters']['properties'])->not->toHaveKey('credits')
        ->and(sentPrompt())->toContain('nothing really costs credits')->not->toContain('any amount of credits')
        ->and($veraInventory->fresh()->credits)->toBeNull()
        ->and($veraInventory->items()->first()->quantity)->toBe(1)
        ->and($yinlinInventory->items()->first()->quantity)->toBe(2);
});
