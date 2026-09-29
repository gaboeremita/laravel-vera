<?php

use App\Enums\RevealSource;
use App\Models\AiModel;
use App\Models\Fact;
use App\Models\KnownFact;
use App\Models\RevealAttempt;
use App\Models\User;
use App\Models\WorldResident;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function factPayload(array $overrides = []): array
{
    return [
        'topic' => 'the keeper\'s last night',
        'content' => 'The keeper rowed out to meet a smuggler and never came back.',
        'disclosure' => 'only in the chapel',
        'relayResidentIds' => [],
        ...$overrides,
    ];
}

it('creates, lists, updates and deletes a resident\'s facts with the residents who can act on them', function () {
    [$user, , , $region, $resident] = worldStateScenario();
    $listener = WorldResident::factory()->create(['region_id' => $region->id]);

    $id = $this->actingAs($user)->postJson(route('worlds.residents.facts.store', [$region->world_id, $resident->id]), factPayload(['relayResidentIds' => [$listener->id]]))
        ->assertCreated()->assertJsonPath('relayResidentIds', [$listener->id])->assertJsonPath('usage', 0)->json('id');

    $this->getJson(route('worlds.residents.facts.index', [$region->world_id, $resident->id]))
        ->assertOk()->assertJsonPath('facts.0.topic', 'the keeper\'s last night')->assertJsonPath('toolsUnsupported', null);

    $this->patchJson(route('worlds.residents.facts.update', [$region->world_id, $resident->id, $id]), factPayload(['disclosure' => 'only while grieving']))
        ->assertOk()->assertJsonPath('disclosure', 'only while grieving')->assertJsonPath('relayResidentIds', []);

    $this->deleteJson(route('worlds.residents.facts.destroy', [$region->world_id, $resident->id, $id]))->assertNoContent();
    expect(Fact::count())->toBe(0);
});

it('refuses a duplicate topic, a holder among the residents who act on it, and a resident of another world', function () {
    [$user, , , $region, $resident] = worldStateScenario();
    worldFact($resident, ['topic' => 'the keeper\'s last night']);
    $stranger = WorldResident::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.residents.facts.store', [$region->world_id, $resident->id]), factPayload(['relayResidentIds' => [$resident->id, $stranger->id]]))
        ->assertJsonValidationErrors(['topic', 'relayResidentIds.0', 'relayResidentIds.1']);
});

it('refuses facts for a resident whose model cannot call tools, and says why on the list when a model loses tool calling later', function () {
    [$user, , , $region, $resident] = worldStateScenario();
    worldFact($resident);
    AiModel::query()->update(['supports_tools' => false]);

    $this->actingAs($user)->postJson(route('worlds.residents.facts.store', [$region->world_id, $resident->id]), factPayload())
        ->assertJsonValidationErrors(['topic' => "model can't call tools"]);

    expect($this->getJson(route('worlds.residents.facts.index', [$region->world_id, $resident->id]))->json('toolsUnsupported'))
        ->toContain("model can't call tools");
});

it('counts the sessions that know a fact, and deleting it clears them but keeps the log', function () {
    [$user, , , $region, $resident, $session] = worldStateScenario();
    $fact = worldFact($resident);
    KnownFact::factory()->create(['world_session_id' => $session->id, 'fact_id' => $fact->id]);
    $attempt = RevealAttempt::factory()->create(['world_session_id' => $session->id, 'fact_id' => $fact->id, 'fact_topic' => $fact->topic]);

    $this->actingAs($user)->getJson(route('worlds.residents.facts.index', [$region->world_id, $resident->id]))->assertJsonPath('facts.0.usage', 1);
    $this->deleteJson(route('worlds.residents.facts.destroy', [$region->world_id, $resident->id, $fact->id]))->assertNoContent();

    expect(KnownFact::count())->toBe(0)
        ->and($attempt->fresh())->fact_id->toBeNull()->fact_topic->toBe($fact->topic)->source->toBe(RevealSource::InCharacter);
});

it('deletes a resident\'s facts when the resident is removed from the region', function () {
    [$user, $assistant, , $region, $resident] = worldStateScenario();
    worldFact($resident);

    $this->actingAs($user)->deleteJson(route('worlds.regions.residents.destroy', [$region->world_id, $region->id, $assistant->id]))->assertSuccessful();

    expect(Fact::count())->toBe(0);
});

it('hides another user\'s world', function () {
    [, , , $region, $resident] = worldStateScenario();
    $fact = worldFact($resident);

    $this->actingAs(User::factory()->create())->getJson(route('worlds.residents.facts.index', [$region->world_id, $resident->id]))->assertForbidden();
    $this->deleteJson(route('worlds.residents.facts.destroy', [$region->world_id, $resident->id, $fact->id]))->assertForbidden();
    expect($fact->fresh())->not->toBeNull();
});

it('turns the review of reveals off and on for a world', function () {
    [$user, , , $region] = worldStateScenario();
    $world = $region->world;

    $this->actingAs($user)->getJson(route('worlds.show', $world->id))->assertJsonPath('reviewReveals', true);
    $this->putJson(route('worlds.update', $world->id), [
        'name' => $world->name, 'slug' => $world->slug, 'description' => $world->description,
        'assistantContextPrompt' => $world->assistant_context_prompt, 'npcContextPrompt' => $world->npc_context_prompt,
        'reviewReveals' => false,
    ])->assertOk()->assertJsonPath('reviewReveals', false);
});

it('lists every fact of the world with its holder for the pickers', function () {
    [$user, $assistant, , $region, $resident] = worldStateScenario();
    worldFact($resident, ['topic' => 'the keeper\'s last night']);
    worldFact(WorldResident::factory()->create());

    $this->actingAs($user)->getJson(route('worlds.facts.index', $region->world_id))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.holderName', $assistant->name);
});
