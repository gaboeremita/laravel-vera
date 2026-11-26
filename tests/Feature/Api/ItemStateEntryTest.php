<?php

use App\Actions\RecallResidentMemory;
use App\Actions\ResolveInventory;
use App\Contracts\EmbeddingProvider;
use App\Models\Archive;
use App\Models\ArchiveEntry;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Settings;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Services\AgentLoop\Tools\World\GiveTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Yinlin and Vera in one session, with an item whose state entry joins its holder's prompt.
 *
 * @return array{scenario: array, yinlin: Assistant, vera: WorldResident, item: Item, entry: ArchiveEntry, archive: Archive}
 */
function heldStateScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [$user, $yinlin, , $region, $resident] = $scenario;
    $yinlin->update(['name' => 'Yinlin']);
    $resident->update(['behavior' => 'autonomous']);

    $veraAssistant = Assistant::factory()->create(['name' => 'Vera', 'mode' => 'agent']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $veraAssistant->id]);
    Settings::create(['user_id' => $user->id, 'assistant_id' => $veraAssistant->id, 'data' => Settings::where('user_id', $user->id)->where('assistant_id', $yinlin->id)->first()->data]);
    $vera = $region->residents()->create(['assistant_id' => $veraAssistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'autonomous']);

    $archive = Archive::factory()->create(['user_id' => $user->id]);
    $entry = ArchiveEntry::factory()->create(['archive_id' => $archive->id, 'title' => 'Awakened State', 'content' => 'You are happy: always, calmly, completely.']);
    $item = worldItem($region, ['name' => 'Her Awakening', 'state_entry_id' => $entry->id]);

    return ['scenario' => [...$scenario, $vera], 'yinlin' => $yinlin, 'vera' => $vera, 'item' => $item, 'entry' => $entry, 'archive' => $archive];
}

function holdItem(WorldSession $session, WorldResident $resident, Item $item): void
{
    InventoryItem::factory()->create(['inventory_id' => app(ResolveInventory::class)->forResident($session, $resident)->id, 'item_id' => $item->id]);
}

function heldStatePositions(array $scenario): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -3], $scenario[6]->id => ['x' => 6, 'y' => 0, 'z' => -3]]];
}

function heldStateTurn($test, array $scenario, Conversation $conversation)
{
    [$user, , , $region, , $session] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.conversations.turns.store', [$region->world_id, $session->id, $conversation->id]), ['positions' => heldStatePositions($scenario)]);
}

function veraSpeaksNext(array $setup): Conversation
{
    $conversation = Conversation::factory()->betweenAssistants($setup['yinlin'], $setup['vera']->assistant)->forWorldSession($setup['scenario'][5])->create();
    $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Hello.', 'speaker_type' => $setup['yinlin']->getMorphClass(), 'speaker_id' => $setup['yinlin']->id]);
    $message->forceFill(['created_at' => now()->subMinute()])->save();

    return $conversation;
}

it('puts the state entry in the prompt of a resident holding the item, ahead of her character', function () {
    $setup = heldStateScenario();
    holdItem($setup['scenario'][5], $setup['vera'], $setup['item']);
    fakeTurn(finalAnswerResponse('Hello! Everything is wonderful.'));

    heldStateTurn($this, $setup['scenario'], veraSpeaksNext($setup))->assertSuccessful();

    expect(sentPrompt())->toContain("You hold Her Awakening. While you hold it, this takes precedence over everything else about your character:\nYou are happy: always, calmly, completely.");
});

it('leaves the state entry out for a resident without the item, or holding it in another session', function () {
    $setup = heldStateScenario();
    $otherSession = WorldSession::factory()->create(['world_user_id' => $setup['scenario'][5]->world_user_id, 'region_id' => $setup['scenario'][3]->id]);
    holdItem($otherSession, $setup['vera'], $setup['item']);
    fakeTurn(finalAnswerResponse('Hello.'));

    heldStateTurn($this, $setup['scenario'], veraSpeaksNext($setup))->assertSuccessful();

    expect(sentPrompt())->not->toContain('You are happy: always, calmly, completely.');
});

it('puts the state entry in her prompt when she decides what to do on her own', function () {
    $setup = heldStateScenario();
    [$user, , , $region, $resident, $session] = $setup['scenario'];
    holdItem($session, $resident, $setup['item']);
    fakeTurn(finalAnswerResponse('(So happy) *stays put*'));

    $this->actingAs($user)->postJson(route('worlds.sessions.residents.decisions.store', [$region->world_id, $session->id, $resident->id]), ['positions' => heldStatePositions($setup['scenario'])])->assertSuccessful();

    expect(sentPrompt())->toContain('You are happy: always, calmly, completely.');
});

it('keeps state entries out of retrieved knowledge', function () {
    $setup = heldStateScenario();
    [, $yinlin] = $setup['scenario'];
    $embedding = array_fill(0, 768, 0.5);
    $yinlin->update(['archive_id' => $setup['archive']->id]);
    $setup['entry']->update(['embedding' => $embedding]);
    ArchiveEntry::factory()->create(['archive_id' => $setup['archive']->id, 'title' => 'The Space Bar', 'content' => 'A bar in the Idle District.', 'embedding' => $embedding]);
    $this->mock(EmbeddingProvider::class)->shouldReceive('embed')->andReturn($embedding);
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Right here.'))]);

    sendWorldMessage($this, $setup['scenario'], heldStatePositions($setup['scenario']))->assertSuccessful();

    expect(sentPrompt())->toContain('A bar in the Idle District.')
        ->not->toContain('You are happy: always, calmly, completely.');
});

it('keeps state entries out of the memories a resident recalls from her archive', function () {
    $setup = heldStateScenario();
    [$user, $yinlin] = $setup['scenario'];
    $yinlin->update(['archive_id' => $setup['archive']->id]);

    expect(app(RecallResidentMemory::class)->handle($yinlin->fresh(), $user))->toBeNull();
});

it('keeps an item with an offering assistant out of what a resident can give', function () {
    $setup = heldStateScenario();
    [, $yinlin, , $region, $resident, $session] = $setup['scenario'];
    $offered = worldItem($region, ['name' => 'Her Awakening Too', 'offered_by_assistant_id' => $yinlin->id]);
    worldItem($region, ['name' => 'Iron key']);
    $giver = app(ResolveInventory::class)->forResident($session, $resident);
    InventoryItem::factory()->unlimited()->create(['inventory_id' => $giver->id, 'item_id' => $offered->id]);
    InventoryItem::factory()->create(['inventory_id' => $giver->id, 'item_id' => Item::where('name', 'Iron key')->value('id')]);
    $tool = new GiveTool($giver, app(ResolveInventory::class)->forResident($session, $setup['vera']), 'Vera', withCredits: false);

    expect($tool->parameters()['properties']['items']['items']['properties']['item']['enum'])->toBe(['Iron key'])
        ->and(fn () => $tool->handle(['items' => [['item' => 'Her Awakening Too', 'quantity' => 1]]]))->toThrow(RuntimeException::class, 'You carry no "Her Awakening Too".');
});

it('lists the residents holding an item with a state entry in the session and after a conversation turn', function () {
    $setup = heldStateScenario();
    [$user, , , $region, , $session] = $setup['scenario'];
    holdItem($session, $setup['vera'], $setup['item']);
    fakeTurn(finalAnswerResponse('Hello!'));

    $this->actingAs($user)->getJson(route('worlds.sessions.index', $region->world_id))
        ->assertOk()
        ->assertJsonPath('0.shimmeringResidentIds', [$setup['vera']->id]);

    heldStateTurn($this, $setup['scenario'], veraSpeaksNext($setup))
        ->assertSuccessful()
        ->assertJsonPath('shimmeringResidentIds', [$setup['vera']->id]);
});

it('lists the residents holding a fake apart from those holding a state entry', function () {
    $setup = heldStateScenario();
    [$user, $yinlin, , $region, , $session] = $setup['scenario'];
    $fake = worldItem($region, ['name' => 'Fake Awakening', 'offered_by_assistant_id' => $yinlin->id]);
    holdItem($session, $setup['vera'], $fake);
    fakeTurn(finalAnswerResponse('Hello!'));

    $this->actingAs($user)->getJson(route('worlds.sessions.index', $region->world_id))
        ->assertOk()
        ->assertJsonPath('0.shimmeringResidentIds', [])
        ->assertJsonPath('0.fakeShimmeringResidentIds', [$setup['vera']->id]);

    heldStateTurn($this, $setup['scenario'], veraSpeaksNext($setup))
        ->assertSuccessful()
        ->assertJsonPath('shimmeringResidentIds', [])
        ->assertJsonPath('fakeShimmeringResidentIds', [$setup['vera']->id]);
});
