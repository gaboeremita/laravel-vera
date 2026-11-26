<?php

use App\Actions\Quests\SyncSessionQuests;
use App\Actions\ResolveResidentRegion;
use App\Actions\StartResidentConversation;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Region;
use App\Models\Sentiment;
use App\Models\WorldSession;
use App\Models\WorldSessionResident;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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

it('lets the resident react in character to what the creator did to them, told as something that came from outside them', function (array $payload, string $fact) {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    WorldSessionResident::create(['world_session_id' => $session->id, 'world_resident_id' => $resident->id, 'region_id' => $region->id, 'position' => ['x' => -2, 'y' => 0, 'z' => 8]]);
    fakeTurn(finalAnswerResponse('*looks at their hands* That was not me.'));
    if (in_array($payload['kind'], ['moved', 'walked'], true)) {
        $payload['fromRegionId'] = $region->id;
    }

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.reaction', [$region->world_id, $session->id, $resident->id]), $payload)
        ->assertSuccessful()
        ->assertJsonPath('line', '*looks at their hands* That was not me.');

    expect(promptOfRequest())->toContain($fact)
        ->toContain('It happened in an instant, and you lived through it')
        ->not->toContain('creator')
        ->and(Conversation::withResident($session, $resident)->messages()->where('role', 'assistant')->latest('id')->value('content'))->toBe('*looks at their hands* That was not me.');
})->with([
    'moved across zones' => [['kind' => 'moved', 'fromZoneName' => 'Pool terrace'], 'Something lifted you and set you down in Vocal booth'],
    'moved within a zone' => [['kind' => 'moved', 'fromZoneName' => 'Vocal booth'], 'Something lifted you and set you down a few steps away'],
    'walked to another zone' => [['kind' => 'walked', 'fromZoneName' => 'Pool terrace', 'toZoneId' => 'vocal-booth'], 'Your legs have just started carrying you from Pool terrace, in '],
    'walked within a zone' => [['kind' => 'walked', 'fromZoneName' => 'Vocal booth', 'toZoneId' => 'vocal-booth'], 'Your legs have just started carrying you across Vocal booth, in '],
    'posed' => [['kind' => 'posed', 'poseName' => 'salute', 'posture' => 'sitting'], 'Your body just moved by itself into "salute" while sitting. The impulse came from outside you'],
    'frozen' => [['kind' => 'frozen'], 'The stillness came from outside you'],
    'unfrozen' => [['kind' => 'unfrozen'], 'Whatever held you still has let go'],
]);

it('refuses a pose reaction that names no pose, a move reaction that names no region, and a walk reaction without a zone of the region', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    $route = route('worlds.sessions.creator.residents.reaction', [$region->world_id, $session->id, $resident->id]);

    $this->actingAs($user)->postJson($route, ['kind' => 'posed'])->assertJsonValidationErrors(['poseName', 'posture']);
    $this->postJson($route, ['kind' => 'moved'])->assertJsonValidationErrors(['fromRegionId']);
    $this->postJson($route, ['kind' => 'walked', 'fromRegionId' => $region->id])->assertJsonValidationErrors(['toZoneId']);
    $this->postJson($route, ['kind' => 'walked', 'fromRegionId' => $region->id, 'toZoneId' => 'no-such-zone'])->assertNotFound();
});

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

it('writes the opening line a resident says when sent to talk to another resident', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    $mara = $region->residents()->create(['assistant_id' => Assistant::factory()->create(['name' => 'Mara'])->id, 'position' => ['x' => 3, 'y' => 0, 'z' => 3], 'behavior' => 'stationary']);
    app(StartResidentConversation::class)->handle($session, $mara, $resident, 'Did you ever fix that amp?');
    fakeTurn(finalAnswerResponse('*waves* Mara, the amp works now.'));

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.opening', [$region->world_id, $session->id, $resident->id]), ['with' => $mara->id])
        ->assertSuccessful()
        ->assertJsonPath('line', '*waves* Mara, the amp works now.');

    expect(promptOfRequest())->toContain('Something outside you has just moved you to go over to Mara')
        ->and(json_encode(Http::recorded()[0][0]['messages']))->toContain('Mara: Did you ever fix that amp?')
        ->and(Conversation::between($resident->assistant, $mara->assistant)->sole()->messages()->count())->toBe(1);
});

it('answers a timed-out opening line with the reason', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    $mara = $region->residents()->create(['assistant_id' => Assistant::factory()->create()->id, 'position' => ['x' => 3, 'y' => 0, 'z' => 3], 'behavior' => 'stationary']);
    Http::fake(fn () => throw new ConnectionException('Operation timed out'));

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.opening', [$region->world_id, $session->id, $resident->id]), ['with' => $mara->id])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Operation timed out');
});

it('refuses to open a conversation with the resident themselves or a resident of another world', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    $stranger = Region::factory()->forUser($user)->create()->residents()->create(['assistant_id' => $resident->assistant_id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);
    $route = route('worlds.sessions.creator.residents.opening', [$region->world_id, $session->id, $resident->id]);

    $this->actingAs($user)->postJson($route, ['with' => $resident->id])->assertNotFound();
    $this->postJson($route, ['with' => $stranger->id])->assertNotFound();
});

it('opens no conversation for a locked session', function () {
    [$user, , , $region, $resident, $session] = creatorResidentScenario();
    $mara = $region->residents()->create(['assistant_id' => Assistant::factory()->create()->id, 'position' => ['x' => 3, 'y' => 0, 'z' => 3], 'behavior' => 'stationary']);
    $session->update(['creator_mode_at' => null]);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.residents.opening', [$region->world_id, $session->id, $resident->id]), ['with' => $mara->id])
        ->assertForbidden();
});

it('continues a regular conversation in the session, and the one it replaces leaves the session', function () {
    [$user, , $regularChat, $region, $resident, $session] = creatorResidentScenario();
    $worldChat = Conversation::withResident($session, $resident);

    $this->actingAs($user)->putJson(route('worlds.sessions.creator.residents.conversation.update', [$region->world_id, $session->id, $resident->id]), ['conversationId' => $regularChat->id])
        ->assertNoContent();

    expect($regularChat->fresh()->world_session_id)->toBe($session->id)
        ->and($worldChat->fresh()->world_session_id)->toBeNull()
        ->and(Conversation::withResident($session, $resident)->id)->toBe($regularChat->id);
});

it('refuses to switch to another user\'s conversation, one in a session, or in a locked session', function () {
    [$user, , $regularChat, $region, $resident, $session] = creatorResidentScenario();
    [, , $strangersChat] = setUpAgentAssistant('assistant');
    $otherSession = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $region->id]);
    $otherSessionChat = Conversation::withResident($otherSession, $resident);
    $route = route('worlds.sessions.creator.residents.conversation.update', [$region->world_id, $session->id, $resident->id]);

    $this->actingAs($user)->putJson($route, ['conversationId' => $strangersChat->id])->assertNotFound();
    $this->putJson($route, ['conversationId' => $otherSessionChat->id])->assertNotFound();

    $session->update(['creator_mode_at' => null]);
    $this->putJson($route, ['conversationId' => $regularChat->id])->assertForbidden();
    expect($regularChat->fresh()->world_session_id)->toBeNull();
});
