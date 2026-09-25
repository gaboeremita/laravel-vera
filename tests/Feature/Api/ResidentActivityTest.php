<?php

use App\Models\ResidentActivity;
use App\Models\User;
use App\Models\World;
use App\Models\WorldSession;
use App\Models\WorldUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function activityRoute(string $name, array $scenario, array $extra = []): string
{
    [, , , $world, $resident, $session] = $scenario;

    return route($name, ['world' => $world->id, 'session' => $session->id, 'resident' => $resident->id, ...$extra]);
}

it('records a requested action with the zone the resident is in', function () {
    $scenario = worldStateScenario();

    $response = $this->actingAs($scenario[0])->postJson(activityRoute('worlds.sessions.residents.activities.store', $scenario), [
        'verb' => 'go_to',
        'target' => 'pool-terrace',
        'position' => ['x' => -2, 'y' => 0, 'z' => 9],
    ]);

    $response->assertCreated();
    $activity = ResidentActivity::findOrFail($response->json('id'));
    expect($activity->source)->toBe('requested')
        ->and($activity->verb)->toBe('go_to')
        ->and($activity->target)->toBe('pool-terrace')
        ->and($activity->zone_id)->toBe('vocal-booth')
        ->and($activity->outcome)->toBeNull();
});

it('records a direct control with its reason', function () {
    $scenario = worldStateScenario();

    $response = $this->actingAs($scenario[0])->postJson(activityRoute('worlds.sessions.residents.activities.store', $scenario), [
        'verb' => 'follow',
        'reason' => 'direct control',
    ]);

    $response->assertCreated();
    expect(ResidentActivity::findOrFail($response->json('id'))->reason)->toBe('direct control');
});

it('records the words she used when she started an action', function () {
    $scenario = worldStateScenario();

    $response = $this->actingAs($scenario[0])->postJson(activityRoute('worlds.sessions.residents.activities.store', $scenario), [
        'verb' => 'use',
        'target' => 'pool-lounger-1-seat',
        'activity' => 'recline',
        'narration' => '*takes her gin tonic out to the lounger*',
    ]);

    $response->assertCreated();
    expect(ResidentActivity::findOrFail($response->json('id'))->narration)->toBe('*takes her gin tonic out to the lounger*');
});

it('rejects unknown verbs', function () {
    $scenario = worldStateScenario();

    $this->actingAs($scenario[0])->postJson(activityRoute('worlds.sessions.residents.activities.store', $scenario), ['verb' => 'teleport'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('verb');
});

it('records the outcome once and refuses to overwrite it', function () {
    $scenario = worldStateScenario();
    $activity = ResidentActivity::factory()->create(['world_session_id' => $scenario[5]->id, 'world_resident_id' => $scenario[4]->id]);

    $this->actingAs($scenario[0])->patchJson(activityRoute('worlds.sessions.residents.activities.update', $scenario, ['activity' => $activity->id]), [
        'outcome' => 'failed',
        'reason' => 'there is no way to get there',
    ])->assertNoContent();

    $activity->refresh();
    expect($activity->outcome)->toBe('failed')
        ->and($activity->outcome_reason)->toBe('there is no way to get there')
        ->and($activity->finished_at)->not->toBeNull();

    $this->actingAs($scenario[0])->patchJson(activityRoute('worlds.sessions.residents.activities.update', $scenario, ['activity' => $activity->id]), [
        'outcome' => 'completed',
    ])->assertUnprocessable();
    expect($activity->fresh()->outcome)->toBe('failed');
});

it('does not reach activities through another user\'s session, world or resident', function () {
    $scenario = worldStateScenario();
    [$user, , , $world, $resident, $session] = $scenario;
    $activity = ResidentActivity::factory()->create(['world_session_id' => $session->id, 'world_resident_id' => $resident->id]);

    $otherUser = User::factory()->create();
    $this->actingAs($otherUser)->postJson(activityRoute('worlds.sessions.residents.activities.store', $scenario), ['verb' => 'stop'])->assertNotFound();
    $this->actingAs($otherUser)->patchJson(activityRoute('worlds.sessions.residents.activities.update', $scenario, ['activity' => $activity->id]), ['outcome' => 'completed'])->assertNotFound();

    $foreignSession = WorldSession::factory()->create();
    $this->actingAs($user)->postJson(route('worlds.sessions.residents.activities.store', ['world' => $world->id, 'session' => $foreignSession->id, 'resident' => $resident->id]), ['verb' => 'stop'])->assertNotFound();

    $otherWorld = World::factory()->forUser($user)->create();
    $otherResident = $otherWorld->residents()->create(['assistant_id' => $resident->assistant_id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);
    $this->actingAs($user)->postJson(route('worlds.sessions.residents.activities.store', ['world' => $world->id, 'session' => $session->id, 'resident' => $otherResident->id]), ['verb' => 'stop'])->assertNotFound();

    $otherActivity = ResidentActivity::factory()->create();
    $this->actingAs($user)->patchJson(activityRoute('worlds.sessions.residents.activities.update', $scenario, ['activity' => $otherActivity->id]), ['outcome' => 'completed'])->assertNotFound();

    expect(WorldUser::where('user_id', $otherUser->id)->exists())->toBeFalse();
});
