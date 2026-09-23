<?php

use App\Enums\Posture;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Pose;
use App\Models\ResidentActivity;
use App\Models\User;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Assistant, 2: Conversation, 3: World, 4: WorldResident, 5: WorldSession}
 */
function autonomousScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    $scenario[4]->update(['behavior' => 'autonomous']);

    return $scenario;
}

function requestDecision($test, array $scenario, array $payload = []): TestResponse
{
    [$user, , , $world, $resident, $session] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.residents.decisions.store', [$world->id, $session->id, $resident->id]), [
        'positions' => [
            'user' => ['x' => -5, 'y' => 0, 'z' => 2],
            'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]],
        ],
        ...$payload,
    ]);
}

it('decides an action, records it and stores the line in her session conversation', function () {
    $scenario = autonomousScenario();
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('(The sun is too good to waste) *stretches out on the lounger*'));

    $response = requestDecision($this, $scenario)
        ->assertCreated()
        ->assertJsonPath('line', '(The sun is too good to waste) *stretches out on the lounger*')
        ->assertJsonPath('action', ['verb' => 'use', 'target' => 'pool-lounger-1-seat', 'activity' => 'recline', 'pose' => null])
        ->assertJsonPath('pose', null);

    $activity = ResidentActivity::find($response->json('activityId'));
    expect($activity)
        ->source->toBe('idle')
        ->verb->toBe('use')
        ->target->toBe('pool-lounger-1-seat')
        ->activity->toBe('recline')
        ->reason->toBe('The sun is too good to waste')
        ->zone_id->toBe('pool-terrace');

    $conversation = Conversation::where('world_session_id', $scenario[5]->id)->sole();
    expect($conversation->messages()->find($response->json('messageId'))->content)->toBe('(The sun is too good to waste) *stretches out on the lounger*');
});

it('reuses the conversation she already has in this session', function () {
    $scenario = autonomousScenario();
    $scenario[2]->update(['world_session_id' => $scenario[5]->id]);
    fakeTurn(finalAnswerResponse('(I like it here) *stays where she is*'));

    requestDecision($this, $scenario)->assertCreated();

    expect(Conversation::where('world_session_id', $scenario[5]->id)->count())->toBe(1);
});

it('decides on a pose or on staying put', function (string $reply, string $verb, ?string $pose) {
    $scenario = autonomousScenario();
    $scenario[1]->update(['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'stretch']);
    fakeTurn(finalAnswerResponse($reply));

    $response = requestDecision($this, $scenario)->assertCreated()->assertJsonPath('action', null)->assertJsonPath('pose', $pose);

    expect(ResidentActivity::find($response->json('activityId'))->verb)->toBe($verb);
})->with([
    'pose' => ['(My back is stiff) *stretches her arms* [pose: stretch]', 'pose', 'stretch'],
    'stay' => ['(Nothing calls me) *stays where she is*', 'stay', null],
]);

it('records the outcome of her previous step', function () {
    $scenario = autonomousScenario();
    $previous = ResidentActivity::factory()->create([
        'world_session_id' => $scenario[5]->id,
        'world_resident_id' => $scenario[4]->id,
        'source' => 'idle',
        'created_at' => now()->subMinute(),
    ]);
    fakeTurn(finalAnswerResponse('(Fine) *stays where she is*'));

    requestDecision($this, $scenario, ['previous' => ['activityId' => $previous->id, 'outcome' => 'failed', 'reason' => 'spot taken']])->assertCreated();

    expect($previous->fresh())->outcome->toBe('failed')->outcome_reason->toBe('spot taken');
});

it('refuses a second decision within 8 seconds', function () {
    $scenario = autonomousScenario();
    ResidentActivity::factory()->create([
        'world_session_id' => $scenario[5]->id,
        'world_resident_id' => $scenario[4]->id,
        'source' => 'idle',
        'created_at' => now()->subSeconds(3),
    ]);

    requestDecision($this, $scenario)->assertTooManyRequests();
});

it('refuses decisions for residents that are not autonomous', function () {
    $scenario = autonomousScenario();
    $scenario[4]->update(['behavior' => 'stationary']);

    requestDecision($this, $scenario)->assertUnprocessable();
});

it('gives an unknown place back to her and records no action', function () {
    $scenario = autonomousScenario();
    fakeTurn(toolCallResponse('call_1', 'go_to', ['target' => 'moon']), finalAnswerResponse('(Never mind) *stays where she is*'));

    requestDecision($this, $scenario)->assertCreated()->assertJsonPath('action', null);
});

it('rejects a spot another resident is using', function () {
    $scenario = autonomousScenario();
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('(Taken) *stays where she is*'));

    requestDecision($this, $scenario, ['occupiedSpots' => ['pool-lounger-1-seat']])->assertCreated()->assertJsonPath('action', null);
    expect(toolResultSentBack())->toContain('pool-lounger-1-seat is taken');
});

it('asks her own model with her persona, the world, her history and what she can do', function () {
    $scenario = autonomousScenario();
    $scenario[1]->update(['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'laugh']);
    Pose::factory()->posture(Posture::Sitting)->create(['assistant_id' => $scenario[1]->id, 'name' => 'laugh']);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'greeting']);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'walk']);
    ResidentActivity::factory()->finished()->create([
        'world_session_id' => $scenario[5]->id,
        'world_resident_id' => $scenario[4]->id,
        'verb' => 'go_to',
        'target' => 'pool-terrace',
        'created_at' => now()->subMinutes(2),
    ]);
    fakeTurn(finalAnswerResponse('(Quiet) *stays where she is*'));

    requestDecision($this, $scenario, ['residentPosture' => 'sitting', 'occupiedSpots' => ['pool-lounger-1-seat']])->assertCreated();

    $prompt = sentSystemPrompt();
    expect($prompt)
        ->toContain('World state:')
        ->toContain('You are in: Pool terrace')
        ->toContain('Your recent activity')
        ->toContain('You are: sitting')
        ->toContain('Poses: laugh (standing, sitting)')
        ->toContain('Things to do in this place: Swim [swim] in Pool terrace')
        ->toContain('pool-lounger-1-seat [pool-lounger-1] at the Pool lounger: Recline [recline] (taken)')
        ->toContain('leaves you to yourself right now')
        ->toContain('vary your activities')
        ->not->toContain('greeting (')
        ->not->toContain('walk (');
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://fake-llm.test/'));
});
