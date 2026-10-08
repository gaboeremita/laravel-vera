<?php

use App\Models\ActivityTerms;
use App\Models\ResidentSentiment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sentimentWorldPayload(array $sentiments): array
{
    return [
        'name' => 'The Pier',
        'slug' => 'the-pier',
        'description' => 'Salt and boards.',
        'assistantContextPrompt' => 'You live on the pier.',
        'npcContextPrompt' => 'You work on the pier.',
        'sentiments' => $sentiments,
    ];
}

it('creates a world with no sentiments, and one with the sentiments it is given', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.store'), [...sentimentWorldPayload([]), 'slug' => 'empty'])
        ->assertCreated()
        ->assertJsonPath('sentiments', []);

    $this->actingAs($user)->postJson(route('worlds.store'), sentimentWorldPayload([['name' => 'awe', 'description' => '-10 is boredom; 10 is wonder.']]))
        ->assertCreated()
        ->assertJsonPath('sentiments', [['name' => 'awe', 'description' => '-10 is boredom; 10 is wonder.']]);
});

it('refuses a sentiment without a description, a repeated name, a name that is not a plain word, and the name reason', function () {
    [$user, , , $region] = worldStateScenario();

    $this->actingAs($user)->patchJson(route('worlds.update', $region->world_id), sentimentWorldPayload([
        ['name' => 'awe', 'description' => ''],
        ['name' => 'Awe', 'description' => 'Again.'],
        ['name' => 'awe: {x}', 'description' => 'Odd.'],
        ['name' => 'reason', 'description' => 'Taken.'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['sentiments.0.description', 'sentiments.1.name', 'sentiments.2.name', 'sentiments.3.name']);
});

it('offers the world\'s sentiments to the condition pickers', function () {
    [$user, , , $region] = worldStateScenario();
    worldSentiments($region->world, ['trust', 'awe']);

    $this->actingAs($user)->getJson(route('worlds.quest-options', $region->world_id))->assertOk()->assertJsonPath('sentiments', ['trust', 'awe']);
});

it('carries a renamed sentiment over to the scores and the conditions that name it, even when two swap', function () {
    [$user, , , $region, $resident, $session] = worldStateScenario();
    worldSentiments($region->world, ['trust', 'liking']);
    ResidentSentiment::of($session, $resident)->adjust(['trust' => 4, 'liking' => -2]);
    $quest = worldQuest($region->world, ['start' => ['mode' => 'condition', 'when' => ['all' => [
        ['sentiment' => ['resident' => $resident->id, 'kind' => 'trust', 'atLeast' => 3]],
        ['sentiment' => ['resident' => $resident->id, 'kind' => 'liking', 'atMost' => 0]],
    ]]]]);
    $terms = ActivityTerms::factory()->create(['region_id' => $region->id, 'object_id' => 'pool-lounger-1', 'activity_id' => 'recline', 'responses' => [
        ['condition' => ['sentiment' => ['resident' => $resident->id, 'kind' => 'trust', 'atLeast' => 1]], 'effects' => []],
    ]]);

    $this->actingAs($user)->patchJson(route('worlds.update', $region->world_id), sentimentWorldPayload([
        ['name' => 'liking', 'description' => 'Was trust.', 'renamedFrom' => 'trust'],
        ['name' => 'faith', 'description' => 'Was liking.', 'renamedFrom' => 'liking'],
    ]))->assertOk()->assertJsonPath('sentiments', [['name' => 'liking', 'description' => 'Was trust.'], ['name' => 'faith', 'description' => 'Was liking.']]);

    expect(ResidentSentiment::of($session, $resident)->fresh()->scores())->toBe(['liking' => 4.0, 'faith' => -2.0])
        ->and(collect($quest->fresh()->definition['start']['when']['all'])->pluck('sentiment.kind')->all())->toBe(['liking', 'faith'])
        ->and($terms->fresh()->responses[0]['condition']['sentiment']['kind'])->toBe('liking');
});

it('keeps the world\'s sentiments when an update leaves them out', function () {
    [$user, , , $region] = worldStateScenario();
    worldSentiments($region->world, ['trust']);
    $payload = sentimentWorldPayload([]);
    unset($payload['sentiments']);

    $this->actingAs($user)->patchJson(route('worlds.update', $region->world_id), $payload)->assertOk()->assertJsonPath('sentiments.0.name', 'trust');
});
