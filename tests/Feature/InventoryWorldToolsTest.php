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
    $scenario = inventoryScenario(playerCredits: 10, residentCredits: 50);
    [, , , $region, , , $player, $resident] = $scenario;
    $bread = worldItem($region, ['name' => 'Bread']);
    InventoryItem::factory()->create(['inventory_id' => $resident->id, 'item_id' => $bread->id, 'quantity' => 3]);
    fakeTurn(toolCallResponse('call_1', 'give', ['credits' => 20, 'items' => [['item' => 'Bread', 'quantity' => 2]]]), finalAnswerResponse('Take these.'));

    sendWorldMessage($this, $scenario, chatPositions($scenario))
        ->assertOk()
        ->assertJsonPath('inventory.credits', 30)
        ->assertJsonPath('changes.credits', 20)
        ->assertJsonPath('changes.items.0.delta', 2);

    expect($resident->fresh()->credits)->toBe(30)
        ->and($resident->items()->first()->quantity)->toBe(1)
        ->and($player->items()->first()->quantity)->toBe(2);
});

it('refuses a hand-over beyond what the resident carries and tells them why', function () {
    $scenario = inventoryScenario(playerCredits: 10, residentCredits: 5);
    [, $assistant, , , , , $player, $resident] = $scenario;
    fakeTurn(toolCallResponse('call_1', 'give', ['credits' => 40]), finalAnswerResponse('Ah, I am short.'));

    sendWorldMessage($this, $scenario, chatPositions($scenario))->assertOk()->assertJsonPath('inventory.credits', 10);

    expect(toolResultSentBack())->toContain("{$assistant->name} has only 5 credits")
        ->and($resident->fresh()->credits)->toBe(5)
        ->and($player->fresh()->credits)->toBe(10);
});

it('keeps an unlimited stock unlimited', function () {
    $scenario = inventoryScenario(playerCredits: 0, residentCredits: null);
    [, , , , , , $player, $resident] = $scenario;
    fakeTurn(toolCallResponse('call_1', 'give', ['credits' => 1000]), finalAnswerResponse('A fortune.'));

    sendWorldMessage($this, $scenario, chatPositions($scenario))->assertOk();

    expect($resident->fresh()->credits)->toBeNull()->and($player->fresh()->credits)->toBe(1000);
});

it('never takes from the player through the resident\'s tools', function () {
    $scenario = inventoryScenario(playerCredits: 100, residentCredits: 0);
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

it('tells a resident what they carry and never what the player carries', function () {
    $scenario = inventoryScenario(playerCredits: 777, residentCredits: 12);
    [, , , $region, , , $player, $resident] = $scenario;
    InventoryItem::factory()->forSale()->create(['inventory_id' => $resident->id, 'item_id' => worldItem($region, ['name' => 'Bread', 'base_price' => 2])->id, 'quantity' => 4]);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => worldItem($region, ['name' => 'Secret map'])->id]);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hello.'))]);

    sendWorldMessage($this, $scenario, chatPositions($scenario))->assertOk();

    expect(sentSystemPrompt())
        ->toContain('You carry 12 credits.')
        ->toContain('4 Bread (for sale, usually 2 credits for one Bread)')
        ->not->toContain('Secret map')
        ->not->toContain('777');
});

it('lets residents hand things to each other while they talk', function () {
    [$user, $yinlin, , $region, $first, $session] = worldStateScenario(fakeReply: false);
    $vera = Assistant::factory()->create(['name' => 'Vera', 'mode' => 'agent']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $vera->id]);
    Settings::create(['user_id' => $user->id, 'assistant_id' => $vera->id, 'data' => Settings::where('user_id', $user->id)->where('assistant_id', $yinlin->id)->first()->data]);
    $second = $region->residents()->create(['assistant_id' => $vera->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'autonomous']);
    $veraInventory = app(ResolveInventory::class)->forResident($session, $second);
    $veraInventory->update(['credits' => 30]);
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera)->forWorldSession($session)->create(['resumed_at' => now()->subMinute()]);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Could you spare a few credits?', 'speaker_type' => $yinlin->getMorphClass(), 'speaker_id' => $yinlin->id])->forceFill(['created_at' => now()->subMinute()])->save();
    fakeTurn(toolCallResponse('call_1', 'give', ['credits' => 10]), finalAnswerResponse('Here you go.'));

    $this->actingAs($user)->postJson(route('worlds.sessions.conversations.turns.store', [$region->world_id, $session->id, $conversation->id]), [
        'positions' => ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$first->id => ['x' => 5, 'y' => 0, 'z' => -3], $second->id => ['x' => 6, 'y' => 0, 'z' => -3]]],
    ])->assertSuccessful();

    expect($veraInventory->fresh()->credits)->toBe(20)
        ->and(app(ResolveInventory::class)->forResident($session, $first)->credits)->toBe(10);
});
