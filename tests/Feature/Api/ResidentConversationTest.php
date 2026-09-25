<?php

use App\Actions\GenerateResidentConversationTurn;
use App\Enums\ConversationStatus;
use App\Models\Assistant;
use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Pose;
use App\Models\Settings;
use App\Models\User;
use App\Models\World;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * The autonomous scenario plus a second resident, Vera, on the same model.
 *
 * @return array{0: User, 1: Assistant, 2: Conversation, 3: World, 4: WorldResident, 5: WorldSession, 6: WorldResident}
 */
function twoResidentScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [$user, $assistant, , $world, $resident] = $scenario;
    $resident->update(['behavior' => 'autonomous']);
    $assistant->update(['name' => 'Yinlin']);

    $vera = Assistant::factory()->create(['name' => 'Vera', 'mode' => 'agent']);
    AssistantUser::factory()->create(['user_id' => $user->id, 'assistant_id' => $vera->id]);
    Settings::create(['user_id' => $user->id, 'assistant_id' => $vera->id, 'data' => Settings::where('user_id', $user->id)->where('assistant_id', $assistant->id)->first()->data]);
    $other = $world->residents()->create(['assistant_id' => $vera->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'autonomous']);

    return [...$scenario, $other];
}

function say(Conversation $conversation, Assistant $speaker, string $content, int $secondsAgo = 60): void
{
    $message = $conversation->messages()->create(['role' => 'assistant', 'content' => $content, 'speaker_type' => $speaker->getMorphClass(), 'speaker_id' => $speaker->id]);
    $message->forceFill(['created_at' => now()->subSeconds($secondsAgo)])->save();
}

function residentPositions(array $scenario): array
{
    return [
        'user' => ['x' => -5, 'y' => 0, 'z' => 2],
        'residents' => [$scenario[4]->id => ['x' => 5, 'y' => 0, 'z' => -3], $scenario[6]->id => ['x' => 6, 'y' => 0, 'z' => -3]],
    ];
}

function decide($test, array $scenario): TestResponse
{
    [$user, , , $world, $resident, $session] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.residents.decisions.store', [$world->id, $session->id, $resident->id]), ['positions' => residentPositions($scenario)]);
}

function takeTurn($test, array $scenario, Conversation $conversation): TestResponse
{
    [$user, , , $world, , $session] = $scenario;

    return $test->actingAs($user)->postJson(route('worlds.sessions.conversations.turns.store', [$world->id, $session->id, $conversation->id]), ['positions' => residentPositions($scenario)]);
}

it('tells her who else is in the world before she decides', function () {
    $scenario = twoResidentScenario();
    fakeTurn(finalAnswerResponse('(Quiet) *stays put*'));

    decide($this, $scenario)->assertCreated();

    expect(sentSystemPrompt())->toContain("Others in this world:\nVera: in Pool terrace")->toContain('about 1 m away from you');
});

it('decides to talk to the user without saving the line before she gets there', function () {
    $scenario = twoResidentScenario();
    fakeTurn(toolCallResponse('call_1', 'talk_to', ['target' => 'user', 'line' => 'Come look at this view.']), finalAnswerResponse('(I miss him) *goes over to him*'));

    decide($this, $scenario)
        ->assertCreated()
        ->assertJsonPath('action.verb', 'talk_to')
        ->assertJsonPath('action.target', 'user')
        ->assertJsonPath('action.line', 'Come look at this view.');

    expect(Message::where('content', 'Come look at this view.')->exists())->toBeFalse();
});

it('decides to talk to a resident without starting the conversation before she gets there', function () {
    $scenario = twoResidentScenario();
    fakeTurn(toolCallResponse('call_1', 'talk_to', ['target' => 'Vera', 'line' => 'Did you hear the organ earlier?']), finalAnswerResponse('(Curious) *walks over to Vera*'));

    decide($this, $scenario)->assertCreated()->assertJsonPath('action.target', (string) $scenario[6]->id);

    expect(Conversation::where('owner_type', (new Assistant)->getMorphClass())->exists())->toBeFalse();
});

it('starts a conversation she owns once she reaches the other resident', function () {
    $scenario = twoResidentScenario();
    [$user, $yinlin, , $world, $resident, $session, $vera] = $scenario;

    $response = $this->actingAs($user)->postJson(route('worlds.sessions.residents.conversations.store', [$world->id, $session->id, $resident->id]), ['with' => $vera->id, 'line' => 'Did you hear the organ earlier?'])
        ->assertCreated();

    $conversation = Conversation::findOrFail($response->json('conversationId'));
    expect($conversation->owner->is($yinlin))->toBeTrue()
        ->and($conversation->counterpart->is($vera->assistant))->toBeTrue()
        ->and($conversation->world_session_id)->toBe($session->id)
        ->and($conversation->status)->toBe(ConversationStatus::Active)
        ->and($conversation->messages()->sole()->only(['content', 'speaker_type', 'speaker_id']))->toBe(['content' => 'Did you hear the organ earlier?', 'speaker_type' => $yinlin->getMorphClass(), 'speaker_id' => $yinlin->id]);
});

it('keeps a resident from starting a conversation with herself', function () {
    $scenario = twoResidentScenario();
    [$user, , , $world, $resident, $session] = $scenario;

    $this->actingAs($user)->postJson(route('worlds.sessions.residents.conversations.store', [$world->id, $session->id, $resident->id]), ['with' => $resident->id, 'line' => 'Hello, me.'])->assertNotFound();
});

it('resumes the pair\'s one conversation whoever starts talking', function () {
    $scenario = twoResidentScenario();
    [$user, $yinlin, , $world, $resident, $session, $vera] = $scenario;
    $stopped = Conversation::factory()->betweenAssistants($vera->assistant, $yinlin)->forWorldSession($session)->create(['status' => ConversationStatus::Paused]);

    $this->actingAs($user)->postJson(route('worlds.sessions.residents.conversations.store', [$world->id, $session->id, $resident->id]), ['with' => $vera->id, 'line' => 'About earlier…'])
        ->assertCreated()
        ->assertJsonPath('conversationId', $stopped->id);

    expect($stopped->fresh()->status)->toBe(ConversationStatus::Active)
        ->and(Conversation::whereNotNull('world_session_id')->count())->toBe(1);
});

it('offers only residents who are free to talk', function () {
    $scenario = twoResidentScenario();
    [, , , , , $session, $vera] = $scenario;
    Conversation::factory()->betweenAssistants($vera->assistant, Assistant::factory()->create())->forWorldSession($session)->create();
    fakeTurn(finalAnswerResponse('(Quiet) *stays put*'));

    decide($this, $scenario)->assertCreated();

    expect(json_encode(Http::recorded()[0][0]['tools']))->not->toContain('"Vera"');
});

it('keeps her from talking to the user while the user is busy with someone else', function () {
    $scenario = twoResidentScenario();
    [$user, , , $world, $resident, $session, $vera] = $scenario;
    fakeTurn(finalAnswerResponse('(He is busy) *waits*'));

    $this->actingAs($user)->postJson(route('worlds.sessions.residents.decisions.store', [$world->id, $session->id, $resident->id]), [
        'positions' => residentPositions($scenario),
        'userBusyWith' => $vera->id,
    ])->assertCreated();

    $talkTo = collect(Http::recorded()[0][0]['tools'])->firstWhere('function.name', 'talk_to');
    expect($talkTo['function']['parameters']['properties']['target']['enum'])->not->toContain('user')
        ->and(sentSystemPrompt())->toContain(', busy talking with Vera');
});

it('refuses to start a conversation with a resident who is talking with someone else', function () {
    $scenario = twoResidentScenario();
    [$user, , , $world, $resident, $session, $vera] = $scenario;
    Conversation::factory()->betweenAssistants($vera->assistant, Assistant::factory()->create())->forWorldSession($session)->create();

    $this->actingAs($user)->postJson(route('worlds.sessions.residents.conversations.store', [$world->id, $session->id, $resident->id]), ['with' => $vera->id, 'line' => 'Vera?'])
        ->assertStatus(409);

    expect(Conversation::whereMorphedTo('owner', $resident->assistant)->exists())->toBeFalse();
});

it('leaves residents the world reports busy out of talk_to and says who they are with', function () {
    $scenario = twoResidentScenario();
    [$user, , , $world, $resident, $session, $vera] = $scenario;
    fakeTurn(finalAnswerResponse('(Busy room) *stays put*'));

    $this->actingAs($user)->postJson(route('worlds.sessions.residents.decisions.store', [$world->id, $session->id, $resident->id]), [
        'positions' => residentPositions($scenario),
        'busyResidents' => [['id' => $vera->id, 'talkingWith' => $resident->id]],
    ])->assertCreated();

    $talkTo = collect(Http::recorded()[0][0]['tools'])->firstWhere('function.name', 'talk_to');
    expect($talkTo['function']['parameters']['properties']['target']['enum'])->not->toContain('Vera')
        ->and(sentSystemPrompt())->toContain('Vera: in Pool terrace')->toContain(', busy with you');
});

it('answers with the resident who did not speak last', function () {
    $scenario = twoResidentScenario();
    [, $yinlin, , , , $session, $vera] = $scenario;
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera->assistant)->forWorldSession($session)->create(['resumed_at' => now()->subMinute()]);
    say($conversation, $yinlin, 'Did you hear the organ?', 10);
    fakeTurn(finalAnswerResponse('I did, it was lovely.'));

    takeTurn($this, $scenario, $conversation)
        ->assertSuccessful()
        ->assertJsonPath('status', 'spoke')
        ->assertJsonPath('message.residentId', $vera->id)
        ->assertJsonPath('message.content', 'I did, it was lovely.');

    expect(sentSystemPrompt())->toContain('You are talking with Yinlin in person');
});

it('plays the pose she picks with her line and drops tags she made up', function () {
    $scenario = twoResidentScenario();
    [, $yinlin, , , , $session, $vera] = $scenario;
    $vera->assistant->update(['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $vera->assistant_id, 'name' => 'laugh']);
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera->assistant)->forWorldSession($session)->create();
    say($conversation, $yinlin, 'Did you hear the organ?', 10);
    fakeTurn(finalAnswerResponse("[amused]\n\n[pose: laugh] I did, it was lovely."));

    takeTurn($this, $scenario, $conversation)
        ->assertSuccessful()
        ->assertJsonPath('message.content', 'I did, it was lovely.')
        ->assertJsonPath('message.pose', 'laugh');

    expect(sentSystemPrompt())->toContain('Use [pose: <exact pose name>] to select a pose')
        ->and(Message::latest('id')->first()->expression)->toBe(['pose' => 'laugh', 'tags' => ['pose' => ['laugh'], 'stray' => ['amused']]]);
});

it('cleans the line she opens with and hands back its pose', function () {
    $scenario = twoResidentScenario();
    $scenario[1]->update(['portrait_type' => 'avatar3d']);
    Pose::factory()->create(['assistant_id' => $scenario[1]->id, 'name' => 'wave']);
    fakeTurn(toolCallResponse('call_1', 'talk_to', ['target' => 'user', 'line' => '[thoughtful] [pose: wave] Come look at this view.']), finalAnswerResponse('(I miss him) *goes over to him*'));

    decide($this, $scenario)
        ->assertCreated()
        ->assertJsonPath('action.line', 'Come look at this view.')
        ->assertJsonPath('action.pose', 'wave')
        ->assertJsonPath('action.expression', ['pose' => 'wave', 'action' => ['verb' => 'talk_to', 'target' => 'user'], 'tags' => ['pose' => ['wave'], 'stray' => ['thoughtful']]]);

    expect(Message::latest('id')->first()->expression['action']['verb'])->toBe('talk_to');
});

it('records how she said the line she opens with once she has said it', function () {
    $scenario = twoResidentScenario();
    [$user, , , $world, $resident, $session, $vera] = $scenario;
    $expression = ['pose' => 'greeting', 'action' => ['verb' => 'talk_to', 'target' => (string) $vera->id]];

    $response = $this->actingAs($user)->postJson(route('worlds.sessions.residents.conversations.store', [$world->id, $session->id, $resident->id]), ['with' => $vera->id, 'line' => 'Vera!', 'expression' => $expression])
        ->assertCreated();

    expect(Conversation::findOrFail($response->json('conversationId'))->messages()->sole()->expression)->toBe($expression);
});

it('waits at least five seconds between lines', function () {
    $scenario = twoResidentScenario();
    [, $yinlin, , , , $session, $vera] = $scenario;
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera->assistant)->forWorldSession($session)->create();
    say($conversation, $yinlin, 'Hi.', 1);
    Http::fake();

    takeTurn($this, $scenario, $conversation)->assertStatus(429)->assertJsonPath('status', 'wait');

    Http::assertNothingSent();
});

it('stops the conversation after ten lines each in one sitting', function () {
    $scenario = twoResidentScenario();
    [, $yinlin, , , , $session, $vera] = $scenario;
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera->assistant)->forWorldSession($session)->create(['resumed_at' => now()->subHour()]);
    foreach (range(1, GenerateResidentConversationTurn::LINES_PER_SITTING * 2) as $index) {
        $speaker = $index % 2 === 1 ? $yinlin : $vera->assistant;
        say($conversation, $speaker, "Line {$index}", 1800);
    }
    Http::fake();

    takeTurn($this, $scenario, $conversation)->assertSuccessful()->assertJsonPath('status', 'paused');

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Paused);
    Http::assertNothingSent();
});

it('stops the conversation when the speaker stops it', function () {
    $scenario = twoResidentScenario();
    [, $yinlin, , , , $session, $vera] = $scenario;
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera->assistant)->forWorldSession($session)->create();
    say($conversation, $yinlin, 'Anyway.');
    fakeTurn(toolCallResponse('call_1', 'stop_conversation', []), finalAnswerResponse('Talk later, then.'));

    takeTurn($this, $scenario, $conversation)->assertSuccessful()->assertJsonPath('status', 'paused')->assertJsonPath('message.content', 'Talk later, then.');

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Paused);
});

it('lets the user stop a conversation and listen in on one', function () {
    $scenario = twoResidentScenario();
    [$user, $yinlin, , $world, , $session, $vera] = $scenario;
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera->assistant)->forWorldSession($session)->create();
    say($conversation, $yinlin, 'Hi.');
    $params = [$world->id, $session->id, $conversation->id];

    $this->actingAs($user)->postJson(route('worlds.sessions.conversations.observers.store', $params))->assertCreated();
    $this->actingAs($user)->getJson(route('worlds.sessions.conversations.show', $params))
        ->assertSuccessful()
        ->assertJsonPath('data.messages.0.speaker.name', 'Yinlin')
        ->assertJsonPath('data.messages.0.content', 'Hi.');
    $this->actingAs($user)->postJson(route('worlds.sessions.conversations.pause', $params))->assertSuccessful()->assertJsonPath('status', 'paused');

    expect($conversation->observers()->sole()->observer->is($user))->toBeTrue()
        ->and($conversation->fresh()->status)->toBe(ConversationStatus::Paused);
});

it('keeps other users out of a world conversation', function () {
    $scenario = twoResidentScenario();
    [, $yinlin, , $world, , $session, $vera] = $scenario;
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera->assistant)->forWorldSession($session)->create();

    $this->actingAs(User::factory()->create())->postJson(route('worlds.sessions.conversations.pause', [$world->id, $session->id, $conversation->id]))->assertNotFound();
});

it('reminds her of her conversations with others when she talks to the user', function () {
    $scenario = twoResidentScenario();
    [, $yinlin, , , $resident, $session, $vera] = $scenario;
    $conversation = Conversation::factory()->betweenAssistants($yinlin, $vera->assistant)->forWorldSession($session)->create();
    say($conversation, $vera->assistant, 'The keytars are his.');
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse('Hi.'))]);

    sendWorldMessage($this, $scenario, residentPositions($scenario))->assertSuccessful();

    expect(sentSystemPrompt())->toContain("With Vera:\nVera: The keytars are his.");
});
