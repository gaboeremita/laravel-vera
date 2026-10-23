<?php

use App\Actions\Quests\SyncSessionQuests;
use App\Actions\ResolveResidentRegion;
use App\Models\Conversation;
use App\Models\Region;
use App\Models\Sentiment;
use App\Models\WorldSessionResident;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function creatorResidentScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, , , $region, , $session] = $scenario;
    $session->update(['creator_mode_at' => now()]);
    $docks = Region::factory()->withLayout()->create(['world_id' => $region->world_id, 'name' => 'Docks']);

    return [...$scenario, $docks];
}

it('summons a resident from another region to stand beside the player', function () {
    [$user, , , $region, $resident, $session, $docks] = creatorResidentScenario();
    WorldSessionResident::create(['world_session_id' => $session->id, 'world_resident_id' => $resident->id, 'region_id' => $docks->id, 'position' => ['x' => 5, 'y' => 0.3, 'z' => -5], 'spot_id' => 'pool-lounger-1-seat', 'activity_id' => 'recline', 'posture' => 'reclining']);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.summon', [$region->world_id, $session->id, $resident->id]), [
        'position' => ['x' => 1, 'y' => 0, 'z' => 2],
        'facing' => 1.5,
    ])->assertNoContent();

    $state = WorldSessionResident::where('world_resident_id', $resident->id)->sole();
    expect($state->region_id)->toBe($region->id)
        ->and($state->position)->toEqual(['x' => 1, 'y' => 0, 'z' => 2])
        ->and($state->spot_id)->toBeNull()
        ->and($state->activity_id)->toBeNull()
        ->and($state->posture->value)->toBe('standing');
});

it('sends a resident to a zone in another region, even one their zone access keeps them out of', function () {
    [$user, , , $region, $resident, $session, $docks] = creatorResidentScenario();
    $layout = $docks->layout;
    $layout['zones'][1]['private'] = true;
    $docks->update(['layout' => $layout]);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.send', [$region->world_id, $session->id, $resident->id]), ['regionId' => $docks->id, 'zoneId' => 'vocal-booth'])
        ->assertSuccessful()
        ->assertJsonPath('regionId', $docks->id);

    $state = WorldSessionResident::where('world_resident_id', $resident->id)->sole();
    expect($state->region_id)->toBe($docks->id)
        ->and($state->position)->toEqual(['x' => -2, 'y' => -2, 'z' => 8]);
});

it('sends a resident home by forgetting where the session put them', function () {
    [$user, , , $region, $resident, $session, $docks] = creatorResidentScenario();
    WorldSessionResident::create(['world_session_id' => $session->id, 'world_resident_id' => $resident->id, 'region_id' => $docks->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0]]);

    $this->actingAs($user)->deleteJson(route('worlds.sessions.creator.residents.state.destroy', [$region->world_id, $session->id, $resident->id]))->assertNoContent();

    expect(WorldSessionResident::where('world_resident_id', $resident->id)->exists())->toBeFalse()
        ->and(app(ResolveResidentRegion::class)->handle($session->fresh(), $resident)->id)->toBe($region->id);
});

it('freezes and unfreezes a resident, and a frozen resident decides nothing', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    $resident->update(['behavior' => 'autonomous']);
    $route = route('worlds.sessions.creator.residents.frozen.update', [$region->world_id, $session->id, $resident->id]);

    $this->actingAs($user)->putJson($route, ['frozen' => true])->assertNoContent();
    expect($session->fresh()->frozen_resident_ids)->toBe([$resident->id]);
    $this->getJson(route('worlds.sessions.index', $region->world_id))->assertJsonPath('0.frozenResidentIds', [$resident->id]);

    $this->postJson(route('worlds.sessions.residents.decisions.store', [$region->world_id, $session->id, $resident->id]), [])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This resident is frozen.');

    $this->putJson($route, ['frozen' => false])->assertNoContent();
    expect($session->fresh()->frozen_resident_ids)->toBe([]);
});

it('sets a resident\'s sentiments, and beats about them react', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    worldSentiments($region->world);
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('trusted', ['when' => ['sentiment' => ['resident' => $resident->id, 'kind' => 'trust', 'atLeast' => 5]]]),
        QuestFactory::beat('later', ['requires' => ['trusted']]),
    ]]);
    app(SyncSessionQuests::class)->handle($session->fresh());

    $this->actingAs($user)->putJson(route('worlds.sessions.creator.residents.sentiments.update', [$region->world_id, $session->id, $resident->id]), ['values' => ['trust' => 5]])
        ->assertSuccessful()
        ->assertJsonPath('sentiments.trust', 5);

    expect(Sentiment::inSession($session, $resident)->scores()['trust'])->toBe(5.0)
        ->and($session->questRuns()->latest('id')->first()->finishedBeats())->toBe(['trusted']);
});

it('refuses sentiment values out of range, fractional or unknown', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    worldSentiments($region->world);
    $route = route('worlds.sessions.creator.residents.sentiments.update', [$region->world_id, $session->id, $resident->id]);

    foreach ([['trust' => 11], ['trust' => -11], ['trust' => 2.5], ['awe' => 3]] as $values) {
        $this->actingAs($user)->putJson($route, ['values' => $values])->assertUnprocessable();
    }
});

it('refuses residents of another world and locked sessions', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    $otherRegion = Region::factory()->forUser($user)->create();
    $stranger = $otherRegion->residents()->create(['assistant_id' => $resident->assistant_id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    $this->actingAs($user)->putJson(route('worlds.sessions.creator.residents.frozen.update', [$region->world_id, $session->id, $stranger->id]), ['frozen' => true])->assertNotFound();

    $session->update(['creator_mode_at' => null]);
    $this->putJson(route('worlds.sessions.creator.residents.frozen.update', [$region->world_id, $session->id, $resident->id]), ['frozen' => true])->assertForbidden();
});

it('lets the resident react in character to each kind of move, knowing where they were and are but not how they got there', function (string $kind, string $fact) {
    [$user, , , $region, $resident, $session, $docks] = creatorResidentScenario();
    WorldSessionResident::create(['world_session_id' => $session->id, 'world_resident_id' => $resident->id, 'region_id' => $region->id, 'position' => ['x' => -2, 'y' => 0, 'z' => 8]]);
    fakeTurn(finalAnswerResponse('*blinks* Where... how did I get here?'));

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.reaction', [$region->world_id, $session->id, $resident->id]), ['kind' => $kind, 'fromRegionId' => $docks->id, 'fromZoneName' => 'Pool terrace'])
        ->assertSuccessful()
        ->assertJsonPath('line', '*blinks* Where... how did I get here?');

    expect(promptOfRequest())->toContain($fact)
        ->toContain('Something happened')
        ->toContain('Imagine what that moment was like for you')
        ->not->toContain('creator power')
        ->toContain('Pool terrace, in Docks')
        ->toContain('Vocal booth, in '.$region->name)
        ->and(Conversation::withResident($session, $resident)->messages()->where('role', 'assistant')->latest('id')->value('content'))->toBe('*blinks* Where... how did I get here?');
})->with([
    'summoned' => ['summoned', 'right beside the user'],
    'followed' => ['followed', 'following the user. Something happened, and now you are both in'],
    'sent' => ['sent', 'Something happened, and now you are in'],
    'sent home' => ['sent_home', 'now you are back home in'],
]);

it('lets the resident change how they feel about the user when they react', function () {
    [$user, , , $region, $resident, $session, $docks] = creatorResidentScenario();
    worldSentiments($region->world, ['trust']);
    fakeTurn(toolCallResponse('feel_1', 'adjust_sentiments', ['trust' => -2, 'reason' => 'They yanked me across the world.']), finalAnswerResponse('Do not do that again.'));

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.reaction', [$region->world_id, $session->id, $resident->id]), ['kind' => 'summoned', 'fromRegionId' => $docks->id])
        ->assertSuccessful();

    expect(Sentiment::inSession($session, $resident)->scores()['trust'])->toBe(-2.0);
});

it('refuses an unknown kind of move', function () {
    [$user, , , $region, $resident, $session, $docks] = creatorResidentScenario();

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.reaction', [$region->world_id, $session->id, $resident->id]), ['kind' => 'kidnapped', 'fromRegionId' => $docks->id])
        ->assertUnprocessable();
});
