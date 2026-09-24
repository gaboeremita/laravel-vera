<?php

use App\Models\AssistantUser;
use App\Models\Conversation;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function postObservation($test, array $scenario, array $payload, ?User $as = null, ?int $residentId = null): TestResponse
{
    [$user, , , $world, $resident, $session] = $scenario;

    return $test->actingAs($as ?? $user)->postJson(route('worlds.sessions.residents.observations.store', [$world->id, $session->id, $residentId ?? $resident->id]), $payload);
}

function sessionConversation(array $scenario): ?Conversation
{
    [$user, $assistant, , , , $session] = $scenario;

    return AssistantUser::where('assistant_id', $assistant->id)->where('user_id', $user->id)->firstOrFail()
        ->conversations()->where('world_session_id', $session->id)->first();
}

it('records what she saw as her own message in her session conversation', function () {
    $scenario = worldStateScenario(fakeReply: false);
    Http::fake();

    $response = postObservation($this, $scenario, ['line' => '*I see the user sit down at the bar counter*'])->assertCreated();

    $conversation = sessionConversation($scenario);
    expect($conversation)->not->toBeNull();
    $message = $conversation->messages()->sole();
    expect($message)
        ->id->toBe($response->json('messageId'))
        ->role->toBe('assistant')
        ->content->toBe('*I see the user sit down at the bar counter*');
    Http::assertNothingSent();
});

it('logs every observation as its own message in the existing session conversation', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , , $session] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    Http::fake();

    postObservation($this, $scenario, ['line' => '*I see the user sit down at the bar counter*'])->assertCreated();
    postObservation($this, $scenario, ['line' => '*I see the user get up from the bar counter*'])->assertCreated();

    expect(sessionConversation($scenario)->id)->toBe($conversation->id);
    expect($conversation->messages()->orderBy('id')->pluck('content')->all())->toBe([
        '*I see the user sit down at the bar counter*',
        '*I see the user get up from the bar counter*',
    ]);
    Http::assertNothingSent();
});

it('refuses another user\'s world session', function () {
    $scenario = worldStateScenario(fakeReply: false);

    postObservation($this, $scenario, ['line' => '*I see the user sit down*'], User::factory()->create())->assertNotFound();
});

it('refuses a resident of another world', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [$user, $assistant] = $scenario;
    $otherWorld = World::factory()->forUser($user)->create();
    $stranger = $otherWorld->residents()->create(['assistant_id' => $assistant->id, 'position' => ['x' => 0, 'y' => 0, 'z' => 0], 'behavior' => 'stationary']);

    postObservation($this, $scenario, ['line' => '*I see the user sit down*'], residentId: $stranger->id)->assertNotFound();
});

it('validates the line', function (array $payload) {
    $scenario = worldStateScenario(fakeReply: false);

    postObservation($this, $scenario, $payload)->assertUnprocessable()->assertJsonValidationErrors('line');
})->with([
    'missing' => [[]],
    'too long' => [['line' => str_repeat('a', 501)]],
]);
