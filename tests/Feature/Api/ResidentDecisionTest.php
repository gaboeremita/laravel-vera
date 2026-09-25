<?php

use App\Enums\Posture;
use App\Models\Archive;
use App\Models\ArchiveEntry;
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

it('offers her thinking and remembering on top of the world tools', function () {
    $scenario = autonomousScenario();
    fakeTurn(finalAnswerResponse('(Quiet) *stays put*'));

    requestDecision($this, $scenario)->assertCreated();

    /** @var Request $request */
    $request = Http::recorded()[0][0];
    expect(collect($request['tools'])->pluck('function.name')->all())->toContain('think')->toContain('remember');
});

it('thinks about something where she is', function () {
    $scenario = autonomousScenario();
    fakeTurn(toolCallResponse('call_1', 'think', ['about' => 'the city lights']), finalAnswerResponse('(They never sleep) *gazes out at the city*'));

    $response = requestDecision($this, $scenario)
        ->assertCreated()
        ->assertJsonPath('action', ['verb' => 'think', 'target' => 'the city lights', 'activity' => null]);

    expect(ResidentActivity::find($response->json('activityId')))
        ->verb->toBe('think')
        ->target->toBe('the city lights')
        ->narration->toBe('*gazes out at the city*');
});

it('remembers something from her time with the user', function () {
    $scenario = autonomousScenario();
    $scenario[2]->update(['long_term_memory' => 'He took her to the faire and laughed at the archery stall.']);
    fakeTurn(toolCallResponse('call_1', 'remember', []), finalAnswerResponse('(That archery stall) *smiles to herself*'));

    requestDecision($this, $scenario)
        ->assertCreated()
        ->assertJsonPath('action', ['verb' => 'remember', 'target' => 'your time with the user', 'activity' => null]);

    expect(toolResultSentBack())->toContain('He took her to the faire and laughed at the archery stall.');
});

it('remembers something from her archive', function () {
    $scenario = autonomousScenario();
    $archive = Archive::factory()->create(['user_id' => $scenario[0]->id]);
    ArchiveEntry::factory()->create(['archive_id' => $archive->id, 'title' => 'The ninth floor', 'content' => 'A shelf nobody has catalogued.']);
    $scenario[1]->update(['archive_id' => $archive->id]);
    fakeTurn(toolCallResponse('call_1', 'remember', []), finalAnswerResponse('(That shelf) *frowns*'));

    requestDecision($this, $scenario)->assertCreated()->assertJsonPath('action.target', 'your archive: The ninth floor');

    expect(toolResultSentBack())->toContain('A shelf nobody has catalogued.');
});

it('tells her nothing comes back when she has no memories yet', function () {
    $scenario = autonomousScenario();
    fakeTurn(toolCallResponse('call_1', 'remember', []), finalAnswerResponse('(Nothing) *stays put*'));

    requestDecision($this, $scenario)->assertCreated()->assertJsonPath('action', null);

    expect(toolResultSentBack())->toContain('Nothing comes back to you right now');
});

it('reads a thought wrapped in emphasis as her reason', function () {
    $scenario = autonomousScenario();
    fakeTurn(toolCallResponse('call_1', 'use', ['spot' => 'pool-lounger-1-seat', 'activity' => 'recline']), finalAnswerResponse('*(The sun is too good to waste)* *stretches out on the lounger*'));

    $response = requestDecision($this, $scenario)->assertCreated();

    expect(ResidentActivity::find($response->json('activityId')))
        ->reason->toBe('The sun is too good to waste')
        ->narration->toBe('*stretches out on the lounger*');
});

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
        ->narration->toBe('*stretches out on the lounger*')
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
    Pose::factory()->posture(Posture::Sitting)->create(['assistant_id' => $scenario[1]->id, 'name' => 'talk']);
    Pose::factory()->restricted()->create(['assistant_id' => $scenario[1]->id, 'name' => 'tease']);
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
        ->toContain("Poses:\nRegular: laugh (standing, sitting)\nRestricted: tease (standing)")
        ->toContain('Things to do in this place: Swim [swim] in Pool terrace')
        ->toContain('pool-lounger-1-seat [pool-lounger-1] at the Pool lounger: Recline [recline] (taken)')
        ->toContain('Read the moment before you choose')
        ->toContain('Let the mood of your last exchange with the user carry into what you do')
        ->not->toContain('leaves you to yourself')
        ->not->toContain('greeting (')
        ->not->toContain('walk (')
        ->not->toContain('talk (');
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://fake-llm.test/'));
});

it('tells her what the user is doing when she decides', function () {
    $scenario = autonomousScenario();
    fakeTurn(finalAnswerResponse('(Quiet) *stays where she is*'));

    requestDecision($this, $scenario, ['userState' => ['posture' => 'reclining', 'spotId' => 'pool-lounger-1-seat', 'activityId' => 'recline']])->assertCreated();

    expect(sentSystemPrompt())->toContain(', reclining on the Pool lounger');
});

it('rejects a decision request whose user state names an unknown spot', function () {
    $scenario = autonomousScenario();
    fakeTurn(finalAnswerResponse('(Quiet) *stays where she is*'));

    requestDecision($this, $scenario, ['userState' => ['posture' => 'sitting', 'spotId' => 'moon-chair']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('userState.spotId');
});

it('shows her the last messages of her session conversation when she decides', function () {
    $scenario = autonomousScenario();
    [, , $conversation, , , $session] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    $longLine = str_repeat('a', 400);
    foreach ([
        ['user', 'the oldest words'],
        ['assistant', 'an old reply'],
        ['user', 'hello there'],
        ['assistant', 'hi yourself'],
        ['user', $longLine],
        ['assistant', 'that is a lot of a'],
        ['user', 'see you'],
        ['assistant', '*I see the user sit down at the bar counter*'],
    ] as [$role, $content]) {
        $conversation->messages()->create(['role' => $role, 'content' => $content]);
    }
    fakeTurn(finalAnswerResponse('(Quiet) *stays where she is*'));

    requestDecision($this, $scenario)->assertCreated();

    expect(sentSystemPrompt())
        ->toContain('Recent conversation, oldest first:')
        ->toContain("the user: hello there\nyou: hi yourself\nthe user: ".str_repeat('a', 300)."\nyou: that is a lot of a\nthe user: see you\nyou: *I see the user sit down at the bar counter*")
        ->not->toContain('the oldest words')
        ->not->toContain('an old reply')
        ->not->toContain(str_repeat('a', 301));
});

it('leaves out the recent conversation when there is none', function () {
    $scenario = autonomousScenario();
    fakeTurn(finalAnswerResponse('(Quiet) *stays where she is*'));

    requestDecision($this, $scenario)->assertCreated();

    expect(sentSystemPrompt())->not->toContain('Recent conversation, oldest first:');
});
