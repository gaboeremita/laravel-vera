<?php

use App\Models\ActivityTerms;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Sentiment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sentimentWorldPayload(array $definitions): array
{
    return [
        'name' => 'The Pier',
        'slug' => 'the-pier',
        'description' => 'Salt and boards.',
        'assistantContextPrompt' => 'You live on the pier.',
        'npcContextPrompt' => 'You work on the pier.',
        'sentimentDefinitions' => $definitions,
    ];
}

it('creates a world with no sentiments, and one with the sentiments it is given', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('worlds.store'), [...sentimentWorldPayload([]), 'slug' => 'empty'])
        ->assertCreated()
        ->assertJsonPath('sentimentDefinitions', []);

    $this->actingAs($user)->postJson(route('worlds.store'), sentimentWorldPayload([['name' => 'awe', 'description' => '-10 is boredom; 10 is wonder.']]))
        ->assertCreated()
        ->assertJsonPath('sentimentDefinitions', [['name' => 'awe', 'description' => '-10 is boredom; 10 is wonder.']]);
});

it('refuses a sentiment without a description, a repeated name, a name that is not a plain word, and the name reason', function () {
    [$user, , , $region] = worldStateScenario();

    $this->actingAs($user)->patchJson(route('worlds.update', $region->world_id), sentimentWorldPayload([
        ['name' => 'awe', 'description' => ''],
        ['name' => 'Awe', 'description' => 'Again.'],
        ['name' => 'awe: {x}', 'description' => 'Odd.'],
        ['name' => 'reason', 'description' => 'Taken.'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['sentimentDefinitions.0.description', 'sentimentDefinitions.1.name', 'sentimentDefinitions.2.name', 'sentimentDefinitions.3.name']);
});

it('offers the world\'s sentiments to the condition pickers', function () {
    [$user, , , $region] = worldStateScenario();
    worldSentiments($region->world, ['trust', 'awe']);

    $this->actingAs($user)->getJson(route('worlds.quest-options', $region->world_id))->assertOk()->assertJsonPath('sentiments', ['trust', 'awe']);
});

it('carries a renamed sentiment over to the scores and the conditions that name it, even when two swap', function () {
    [$user, , , $region, $resident, $session] = worldStateScenario();
    worldSentiments($region->world, ['trust', 'liking']);
    Sentiment::inSession($session, $resident)->adjust(['trust' => 4, 'liking' => -2]);
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
    ]))->assertOk()->assertJsonPath('sentimentDefinitions', [['name' => 'liking', 'description' => 'Was trust.'], ['name' => 'faith', 'description' => 'Was liking.']]);

    expect(Sentiment::inSession($session, $resident)->fresh()->scores())->toBe(['liking' => 4.0, 'faith' => -2.0])
        ->and(collect($quest->fresh()->definition['start']['when']['all'])->pluck('sentiment.kind')->all())->toBe(['liking', 'faith'])
        ->and($terms->fresh()->responses[0]['condition']['sentiment']['kind'])->toBe('liking');
});

it('keeps the world\'s sentiments when an update leaves them out', function () {
    [$user, , , $region] = worldStateScenario();
    worldSentiments($region->world, ['trust']);
    $payload = sentimentWorldPayload([]);
    unset($payload['sentimentDefinitions']);

    $this->actingAs($user)->patchJson(route('worlds.update', $region->world_id), $payload)->assertOk()->assertJsonPath('sentimentDefinitions.0.name', 'trust');
});

it('creates an assistant with the sentiment definitions it is given and shows them', function () {
    $user = User::factory()->create();

    $created = $this->actingAs($user)->post(route('assistants.store'), [
        'name' => 'Iris',
        'slug' => 'iris',
        'portrait_type' => 'avatar3d',
        'sentimentDefinitions' => json_encode([['name' => 'warmth', 'description' => '-10 is ice; 10 is sunshine.', 'renamedFrom' => null]]),
    ], ['Accept' => 'application/json'])->assertCreated()->json();

    $this->actingAs($user)->getJson(route('assistants.show', $created['id']))
        ->assertOk()
        ->assertJsonPath('sentimentDefinitions', [['name' => 'warmth', 'description' => '-10 is ice; 10 is sunshine.']]);
});

it('carries a renamed assistant sentiment over to the scores of chats outside worlds only', function () {
    [$user, , , $region, $resident, $session] = worldStateScenario();
    $assistant = $resident->assistant;
    $assistant->update(['sentiment_definitions' => [['name' => 'trust', 'description' => 'How much.']]]);
    worldSentiments($region->world, ['trust']);
    $chat = Conversation::factory()->forAssistantUser(AssistantUser::where('assistant_id', $assistant->id)->where('user_id', $user->id)->firstOrFail())->create();
    Sentiment::of($chat)->adjust(['trust' => 4]);
    Sentiment::inSession($session, $resident)->adjust(['trust' => 2]);

    $this->actingAs($user)->patchJson(route('assistants.update', $assistant->id), [
        'sentimentDefinitions' => [['name' => 'faith', 'description' => 'Was trust.', 'renamedFrom' => 'trust']],
    ])->assertOk();

    expect(Assistant::find($assistant->id)->sentiment_definitions)->toBe([['name' => 'faith', 'description' => 'Was trust.']])
        ->and(Sentiment::of($chat)->fresh()->scores())->toBe(['faith' => 4.0])
        ->and(Sentiment::inSession($session, $resident)->scores())->toBe(['trust' => 2.0]);
});

it('refuses an assistant sentiment named reason', function () {
    [$user, $assistant] = setUpAgentAssistant('assistant');

    $this->actingAs($user)->patchJson(route('assistants.update', $assistant->id), [
        'sentimentDefinitions' => [['name' => 'reason', 'description' => 'Taken.']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['sentimentDefinitions.0.name']);
});
