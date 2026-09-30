<?php

use App\Enums\RevealSource;
use App\Models\ActivityTerms;
use App\Models\AiModel;
use App\Models\Fact;
use App\Models\InventoryItem;
use App\Models\KnownFact;
use App\Models\RevealAttempt;
use App\Models\User;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function playScenario(): array
{
    $scenario = inventoryScenario(playerCredits: 0);
    $scenario[3]->world->update(['narrator_model_id' => AiModel::first()->id]);

    return [...$scenario, worldFact($scenario[4], ['topic' => 'the keeper\'s last night', 'content' => 'The keeper met a smuggler.'])];
}

it('lists what the player learned, newest first, with summary and source', function () {
    [$user, , , $region, $resident, $session, , , $fact] = playScenario();
    $older = worldFact($resident, ['topic' => 'the mayor\'s debts']);
    KnownFact::factory()->create(['world_session_id' => $session->id, 'fact_id' => $older->id, 'source_name' => 'Torn letter', 'summary' => 'The mayor owes the guild.']);
    KnownFact::factory()->create(['world_session_id' => $session->id, 'fact_id' => $fact->id, 'source_name' => 'Ines', 'summary' => 'The keeper met a smuggler.']);

    $this->actingAs($user)->getJson(route('worlds.sessions.known-facts.index', [$region->world_id, $session->id]))
        ->assertOk()
        ->assertJsonPath('0.topic', 'the keeper\'s last night')
        ->assertJsonPath('0.sourceName', 'Ines')
        ->assertJsonPath('1.summary', 'The mayor owes the guild.');
});

it('shows an empty list when nothing is learned, and hides another user\'s session', function () {
    [$user, , , $region, , $session] = playScenario();

    $this->actingAs($user)->getJson(route('worlds.sessions.known-facts.index', [$region->world_id, $session->id]))->assertOk()->assertExactJson([]);
    $this->actingAs(User::factory()->create())->getJson(route('worlds.sessions.known-facts.index', [$region->world_id, $session->id]))->assertNotFound();
});

it('writes the summary as the fixed sentence when the reply tells nothing', function () {
    $scenario = playScenario();
    [, , , , $resident] = $scenario;
    fakeTurn(
        toolCallResponse('call_1', 'reveal', ['fact' => 'the keeper\'s last night', 'reason' => 'they asked']),
        toolCallResponse('verdict_1', 'verdict', ['approved' => true, 'verdict' => 'Fine.']),
        finalAnswerResponse('Let us talk about the weather instead.'),
        finalAnswerResponse('You haven\'t heard the details yet.'),
    );

    sendWorldMessage($this, $scenario, ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]])
        ->assertOk()
        ->assertJsonPath('learnedFacts.0.summary', 'You haven\'t heard the details yet.');
});

it('learns a fact linked to an item when the player examines it, with the narration as summary', function () {
    [$user, , , $region, , $session, $player, , $fact] = playScenario();
    $letter = worldItem($region, ['name' => 'Torn letter', 'reveals_fact_id' => $fact->id]);
    InventoryItem::factory()->create(['inventory_id' => $player->id, 'item_id' => $letter->id]);
    fakeTurn(toolCallResponse('call_1', 'narrate', ['succeeded' => true, 'narration' => 'The letter says the keeper met a smuggler.', 'action' => 'reads a letter']));

    $this->actingAs($user)->getJson(route('worlds.sessions.items.examine', [$region->world_id, $session->id, $letter->id]))
        ->assertOk()
        ->assertJsonPath('learnedFacts.0.summary', 'The letter says the keeper met a smuggler.')
        ->assertJsonPath('learnedFacts.0.sourceName', 'Torn letter');

    expect(KnownFact::sole())->source->toBe(RevealSource::Item)
        ->and(RevealAttempt::sole())->source->toBe(RevealSource::Item)->reviewed->toBeFalse();
});

it('learns nothing from an activity whose requirement is not met', function () {
    [$user, , , $region, , $session, , , $fact] = playScenario();
    ActivityTerms::factory()->withEffects([['type' => 'revealFact', 'fact' => $fact->id]], ['narrator' => ['requirement' => 'only for guild members', 'outcome' => '']])
        ->create(['region_id' => $region->id, 'object_id' => 'pool-lounger-1', 'activity_id' => 'recline']);
    fakeTurn(toolCallResponse('call_1', 'narrate', ['succeeded' => false, 'narration' => 'The attendant shoos you away.', 'action' => 'is turned away']));

    $this->actingAs($user)->postJson(route('worlds.sessions.activity-uses.store', [$region->world_id, $session->id]), [
        'regionId' => $region->id, 'objectId' => 'pool-lounger-1', 'activityId' => 'recline',
    ])->assertOk()->assertJsonPath('allowed', false)->assertJsonPath('learnedFacts', []);

    expect(KnownFact::count())->toBe(0);
});

it('refuses to link a fact of another world', function () {
    [$user, , , $region] = playScenario();
    $stranger = Fact::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.items.store', $region->world_id), [
        'name' => 'Letter', 'description' => 'A letter.', 'revealsFactId' => $stranger->id,
    ])->assertJsonValidationErrors('revealsFactId');
});

it('lists every reveal attempt of the session in order, readable after the fact is gone', function () {
    [$user, , , $region, , $session, , , $fact] = playScenario();
    RevealAttempt::factory()->create(['world_session_id' => $session->id, 'fact_id' => $fact->id, 'fact_topic' => $fact->topic, 'holder_name' => 'Ines', 'reason' => 'first try']);
    RevealAttempt::factory()->approved()->create(['world_session_id' => $session->id, 'fact_id' => $fact->id, 'fact_topic' => $fact->topic, 'holder_name' => 'Ines', 'reason' => 'second try']);
    $fact->delete();

    $this->actingAs($user)->getJson(route('worlds.sessions.reveal-attempts.index', [$region->world_id, $session->id]))
        ->assertOk()
        ->assertJsonPath('0.reason', 'first try')
        ->assertJsonPath('0.approved', false)
        ->assertJsonPath('1.approved', true)
        ->assertJsonPath('1.factTopic', 'the keeper\'s last night')
        ->assertJsonPath('1.holderName', 'Ines');
});

it('shows an empty reveal log, and hides another user\'s session', function () {
    [$user, , , $region, , $session] = playScenario();
    $other = WorldSession::factory()->create();

    $this->actingAs($user)->getJson(route('worlds.sessions.reveal-attempts.index', [$region->world_id, $session->id]))->assertExactJson([]);
    $this->getJson(route('worlds.sessions.reveal-attempts.index', [$region->world_id, $other->id]))->assertNotFound();
});
