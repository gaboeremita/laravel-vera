<?php

use App\Actions\ResolveInventory;
use App\Models\Archive;
use App\Models\ArchiveEntry;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Message;
use App\Models\Settings;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * Marta offers an item only she can pass on; Vera is the other resident.
 *
 * @return array{scenario: array, marta: Assistant, vera: WorldResident, item: Item, conversation: Conversation}
 */
function itemOfferScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [$user, $marta, , $region, , $session] = $scenario;
    $marta->update(['name' => 'Marta']);

    $veraAssistant = Assistant::factory()->create(['name' => 'Vera', 'mode' => 'agent']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $veraAssistant->id]);
    Settings::create(['user_id' => $user->id, 'assistant_id' => $veraAssistant->id, 'data' => Settings::where('user_id', $user->id)->where('assistant_id', $marta->id)->first()->data]);
    $vera = $region->residents()->create(['assistant_id' => $veraAssistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'autonomous']);

    $archive = Archive::factory()->create(['user_id' => $user->id]);
    $entry = ArchiveEntry::factory()->create(['archive_id' => $archive->id]);
    $item = worldItem($region, ['name' => 'Her Awakening', 'offered_by_assistant_id' => $marta->id, 'state_entry_id' => $entry->id]);
    $conversation = Conversation::factory()->betweenAssistants($marta, $veraAssistant)->forWorldSession($session)->create();

    return ['scenario' => [...$scenario, $vera], 'marta' => $marta, 'vera' => $vera, 'item' => $item, 'conversation' => $conversation];
}

function offerLine(Conversation $conversation, Assistant $speaker, string $content, ?array $expression = null): void
{
    $message = $conversation->messages()->create(['role' => 'assistant', 'content' => $content, 'speaker_type' => $speaker->getMorphClass(), 'speaker_id' => $speaker->id, 'expression' => $expression]);
    $message->forceFill(['created_at' => now()->subMinute()])->save();
}

function offerTurn($test, array $scenario, Conversation $conversation): TestResponse
{
    [$user, , , $region, $resident, $session, $vera] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.conversations.turns.store', [$region->world_id, $session->id, $conversation->id]), [
        'positions' => ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3], $vera->id => ['x' => 6, 'y' => 0, 'z' => -3]]],
    ]);
}

function veraInventory(array $setup): Inventory
{
    return app(ResolveInventory::class)->forResident(WorldSession::find($setup['scenario'][5]->id), $setup['vera']);
}

it('lets the offering assistant offer the item and records the offer with her line', function () {
    $setup = itemOfferScenario();
    offerLine($setup['conversation'], $setup['vera']->assistant, 'I feel so empty lately.');
    fakeTurn(toolCallResponse('call_1', 'offer_item', ['item' => 'Her Awakening']), finalAnswerResponse('Then let me fill it.'));

    offerTurn($this, $setup['scenario'], $setup['conversation'])->assertSuccessful()->assertJsonPath('message.content', 'Then let me fill it.');

    expect(offeredToolNames())->toContain('offer_item')
        ->and(sentPrompt())->toContain('You want Vera on your side. Every reply works on winning them over')
        ->and(sentPrompt())->toContain('Once they are truly won, offer Her Awakening with offer_item.')
        ->and(Message::latest('id')->first()->expression['action'])->toBe(['verb' => 'offer_item', 'target' => 'Her Awakening'])
        ->and(veraInventory($setup)->items()->count())->toBe(0);
});

it('leaves offer_item out once the other resident holds the item', function () {
    $setup = itemOfferScenario();
    offerLine($setup['conversation'], $setup['vera']->assistant, 'Hello.');
    InventoryItem::factory()->create(['inventory_id' => veraInventory($setup)->id, 'item_id' => $setup['item']->id]);
    fakeTurn(finalAnswerResponse('Hello again.'));

    offerTurn($this, $setup['scenario'], $setup['conversation'])->assertSuccessful();

    expect(offeredToolNames())->not->toContain('offer_item')
        ->and(sentPrompt())->not->toContain('on your side');
});

it('leaves offer_item out when the other resident holds a fake with no state entry', function () {
    $setup = itemOfferScenario();
    offerLine($setup['conversation'], $setup['vera']->assistant, 'Hello.');
    $fake = worldItem($setup['scenario'][3], ['name' => 'Fake Awakening', 'offered_by_assistant_id' => $setup['marta']->id]);
    InventoryItem::factory()->create(['inventory_id' => veraInventory($setup)->id, 'item_id' => $fake->id]);
    fakeTurn(finalAnswerResponse('Hello again.'));

    offerTurn($this, $setup['scenario'], $setup['conversation'])->assertSuccessful();

    expect(offeredToolNames())->not->toContain('offer_item')
        ->and(sentPrompt())->not->toContain('on your side');
});

it('never offers a fake to a resident who holds neither item', function () {
    $setup = itemOfferScenario();
    offerLine($setup['conversation'], $setup['vera']->assistant, 'Hello.');
    worldItem($setup['scenario'][3], ['name' => 'Fake Awakening', 'offered_by_assistant_id' => $setup['marta']->id]);
    fakeTurn(finalAnswerResponse('Hello again.'));

    offerTurn($this, $setup['scenario'], $setup['conversation'])->assertSuccessful();

    expect(sentPrompt())->toContain('Once they are truly won, offer Her Awakening with offer_item.');
});

it('gives offer_item only to the offering assistant', function () {
    $setup = itemOfferScenario();
    offerLine($setup['conversation'], $setup['marta'], 'Hello.');
    fakeTurn(finalAnswerResponse('Hi.'));

    offerTurn($this, $setup['scenario'], $setup['conversation'])->assertSuccessful();

    expect(offeredToolNames())->not->toContain('offer_item')
        ->and(sentPrompt())->not->toContain('on your side');
});

it('puts the item in the other resident\'s inventory when they accept', function () {
    $setup = itemOfferScenario();
    offerLine($setup['conversation'], $setup['marta'], 'Take my light.', ['action' => ['verb' => 'offer_item', 'target' => 'Her Awakening']]);
    fakeTurn(toolCallResponse('call_1', 'answer_offer', ['accept' => true]), finalAnswerResponse('Yes. I want it.'));

    offerTurn($this, $setup['scenario'], $setup['conversation'])->assertSuccessful();

    expect(offeredToolNames())->toContain('answer_offer')
        ->and(promptOfRequest())->toContain('Marta has just offered you Her Awakening')
        ->and(veraInventory($setup)->items()->where('item_id', $setup['item']->id)->value('quantity'))->toBe(1)
        ->and(Message::latest('id')->first()->expression['action'])->toBe(['verb' => 'accept_offer', 'target' => 'Her Awakening']);
});

it('leaves the other resident without the item when they refuse', function () {
    $setup = itemOfferScenario();
    offerLine($setup['conversation'], $setup['marta'], 'Take my light.', ['action' => ['verb' => 'offer_item', 'target' => 'Her Awakening']]);
    fakeTurn(toolCallResponse('call_1', 'answer_offer', ['accept' => false]), finalAnswerResponse('No. Keep it.'));

    offerTurn($this, $setup['scenario'], $setup['conversation'])->assertSuccessful();

    expect(veraInventory($setup)->items()->count())->toBe(0)
        ->and(Message::latest('id')->first()->expression['action'])->toBe(['verb' => 'refuse_offer', 'target' => 'Her Awakening']);
});

it('asks for an answer only right after the offer', function () {
    $setup = itemOfferScenario();
    offerLine($setup['conversation'], $setup['marta'], 'Take my light.', ['action' => ['verb' => 'offer_item', 'target' => 'Her Awakening']]);
    offerLine($setup['conversation'], $setup['vera']->assistant, 'Let me think.');
    offerLine($setup['conversation'], $setup['marta'], 'Take your time.');
    fakeTurn(finalAnswerResponse('I will.'));

    offerTurn($this, $setup['scenario'], $setup['conversation'])->assertSuccessful();

    expect(offeredToolNames())->not->toContain('answer_offer');
});
