<?php

use App\Models\Fact;
use App\Models\Item;
use App\Models\Quest;
use App\Models\Region;
use App\Models\User;
use App\Models\WorldSessionQuest;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function questPayload(array $definition = [], array $overrides = []): array
{
    return ['key' => 'the-flooded-mill', 'title' => 'The Flooded Mill', 'campaignId' => null, 'definition' => [...QuestFactory::defaultDefinition(), ...$definition], ...$overrides];
}

it('creates, lists, updates and deletes a world\'s quests', function () {
    [$user, , , $region, , $session] = worldStateScenario();

    $id = $this->actingAs($user)->postJson(route('worlds.quests.store', $region->world_id), questPayload())
        ->assertCreated()->assertJsonPath('quest.key', 'the-flooded-mill')->assertJsonPath('warnings.0', fn ($warning) => str_contains($warning, 'firstDone'))->json('quest.id');
    WorldSessionQuest::factory()->create(['world_session_id' => $session->id, 'quest_id' => $id]);

    $this->getJson(route('worlds.quests.index', $region->world_id))
        ->assertOk()->assertJsonPath('0.title', 'The Flooded Mill')->assertJsonPath('0.sessionCount', 1)->assertJsonPath('0.problems', []);

    $this->patchJson(route('worlds.quests.update', [$region->world_id, $id]), questPayload(['description' => 'Rewritten.']))
        ->assertOk()->assertJsonPath('quest.definition.description', 'Rewritten.');

    $this->deleteJson(route('worlds.quests.destroy', [$region->world_id, $id]))->assertNoContent();
    expect(Quest::count())->toBe(0)->and(WorldSessionQuest::count())->toBe(0);
});

it('refuses a definition with problems, listing each under its path', function () {
    [$user, , , $region] = worldStateScenario();

    $this->actingAs($user)->postJson(route('worlds.quests.store', $region->world_id), questPayload(['beats' => [QuestFactory::beat('go', ['when' => ['enterRegion' => 999], 'requires' => ['go']])]], ['key' => 'Not A Key']))
        ->assertJsonValidationErrors(['key']);

    $this->postJson(route('worlds.quests.store', $region->world_id), questPayload(['beats' => [QuestFactory::beat('go', ['when' => ['enterRegion' => 999], 'requires' => ['go']])]]))
        ->assertJsonValidationErrors(['definition.beats.0.when.enterRegion', 'definition.beats.0.requires.0']);
});

it('lists a problem once a new layout removes a zone a quest names', function () {
    [$user, , , $region] = worldStateScenario();
    worldQuest($region->world, ['beats' => [QuestFactory::beat('go', ['when' => ['enterZone' => ['region' => $region->id, 'zone' => 'studio']]])]]);

    $region->update(['layout' => [...$region->layout, 'zones' => []]]);

    expect($this->actingAs($user)->getJson(route('worlds.quests.index', $region->world_id))->json('0.problems.0'))->toContain('has no zone "studio"');
});

it('gives the editor every choice of the world in one call', function () {
    [$user, , , $region, $resident] = worldStateScenario();
    worldItem($region, ['name' => 'Ledger']);
    worldFact($resident, ['topic' => 'the flood']);
    worldQuest($region->world, ['beats' => [QuestFactory::beat('go', ['grants' => [['resident' => $resident->id, 'flag' => 'trusted']], 'when' => ['flag' => 'trusted']])]], ['key' => 'first']);

    $this->actingAs($user)->getJson(route('worlds.quest-options', $region->world_id))
        ->assertOk()
        ->assertJsonPath('regions.0.zones.0.id', 'studio')
        ->assertJsonPath('regions.0.objects.0.activities.0.id', 'recline')
        ->assertJsonPath('residents.0.id', $resident->id)
        ->assertJsonPath('items.0.name', 'Ledger')
        ->assertJsonPath('facts.0.topic', 'the flood')
        ->assertJsonPath('quests.0.flags', ['trusted']);
});

it('refuses to delete what a quest names, naming the quest', function () {
    [$user, $assistant, , $region, $resident] = worldStateScenario();
    $item = worldItem($region);
    $fact = worldFact($resident);
    worldQuest($region->world, ['beats' => [QuestFactory::beat('go', ['when' => ['all' => [
        ['has' => ['item' => $item->id, 'atLeast' => 1]],
        ['knows' => $fact->id],
        ['talkTo' => $resident->id],
        ['enterRegion' => $region->id],
    ]]])]], ['title' => 'The Flooded Mill']);

    $this->actingAs($user)->deleteJson(route('worlds.items.destroy', [$region->world_id, $item->id]))->assertUnprocessable()->assertJsonPath('quests.0.title', 'The Flooded Mill');
    $this->deleteJson(route('worlds.residents.facts.destroy', [$region->world_id, $resident->id, $fact->id]))->assertUnprocessable();
    $this->deleteJson(route('worlds.regions.residents.destroy', [$region->world_id, $region->id, $assistant->id]))->assertUnprocessable();
    $this->deleteJson(route('worlds.regions.destroy', [$region->world_id, $region->id]))->assertUnprocessable();

    expect(Item::count())->toBe(1)->and(Fact::count())->toBe(1)->and(Region::count())->toBe(1)->and($resident->fresh())->not->toBeNull();
});

it('keeps another user\'s world out of reach', function () {
    [, , , $region] = worldStateScenario();

    $this->actingAs(User::factory()->create())->getJson(route('worlds.quests.index', $region->world_id))->assertForbidden();
    $this->getJson(route('worlds.quest-options', $region->world_id))->assertForbidden();
});

it('refuses to delete a quest another quest checks, and what the new conditions name', function () {
    [$user, $assistant, , $region, $resident] = worldStateScenario();
    $item = worldItem($region);
    $ledger = worldQuest($region->world, [], ['key' => 'the-ledger', 'title' => 'The Ledger']);
    worldQuest($region->world, ['start' => ['mode' => 'offer', 'giver' => $resident->id, 'offerWhen' => ['all' => [
        ['declinedTimes' => ['quest' => 'the-ledger', 'atLeast' => 1]],
        ['gaveTo' => ['resident' => $resident->id, 'item' => $item->id, 'atLeast' => 1]],
    ]]]], ['key' => 'the-watcher', 'title' => 'The Watcher']);

    $this->actingAs($user)->deleteJson(route('worlds.quests.destroy', [$region->world_id, $ledger->id]))
        ->assertUnprocessable()->assertJsonPath('quests.0.title', 'The Watcher');
    $this->deleteJson(route('worlds.items.destroy', [$region->world_id, $item->id]))->assertUnprocessable()->assertJsonPath('quests.0.title', 'The Watcher');
    $this->deleteJson(route('worlds.regions.residents.destroy', [$region->world_id, $region->id, $assistant->id]))->assertUnprocessable();

    $selfWatching = worldQuest($region->world, ['beats' => [QuestFactory::beat('again', ['when' => ['questState' => ['quest' => 'lonely', 'state' => 'abandoned']]])]], ['key' => 'lonely']);
    $this->deleteJson(route('worlds.quests.destroy', [$region->world_id, $selfWatching->id]))->assertNoContent();
});
