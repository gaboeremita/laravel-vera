<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function replyWith(string $reply): void
{
    Http::fake(['fake-llm.test/*' => Http::response(finalAnswerResponse($reply))]);
}

function sendResidentMessage($test, array $scenario): Illuminate\Testing\TestResponse
{
    return sendWorldMessage($test, $scenario, [
        'user' => ['x' => 5, 'y' => 0, 'z' => -3],
        'residents' => [$scenario[4]->id => ['x' => -5, 'y' => 0, 'z' => 2]],
    ]);
}

it('returns the action a resident chose in her reply', function (string $reply, array $expected) {
    $scenario = worldStateScenario();
    replyWith($reply);

    sendResidentMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', $expected);
})->with([
    'go to a zone' => ['On my way! [action: go_to pool-terrace]', ['verb' => 'go_to', 'target' => 'pool-terrace', 'activity' => null]],
    'go to an object' => ['Sure. [action: go_to pool-lounger-1]', ['verb' => 'go_to', 'target' => 'pool-lounger-1', 'activity' => null]],
    'follow' => ['Lead the way. [action: follow]', ['verb' => 'follow', 'target' => null, 'activity' => null]],
    'stop' => ['Okay, stopping. [action: stop]', ['verb' => 'stop', 'target' => null, 'activity' => null]],
    'use a spot' => ['Time to relax. [action: use pool-lounger-1-seat recline]', ['verb' => 'use', 'target' => 'pool-lounger-1-seat', 'activity' => 'recline']],
    'id written with underscores' => ['On my way! [action: go_to pool_terrace]', ['verb' => 'go_to', 'target' => 'pool-terrace', 'activity' => null]],
    'place written by name' => ['On my way! [action: go_to Pool Terrace]', ['verb' => 'go_to', 'target' => 'pool-terrace', 'activity' => null]],
    'only the first action counts' => ['[action: follow] Actually no. [action: stop]', ['verb' => 'follow', 'target' => null, 'activity' => null]],
]);

it('marks actions that name unknown things or verbs as invalid with a reason', function (string $reply, string $reason) {
    $scenario = worldStateScenario();
    replyWith($reply);

    $response = sendResidentMessage($this, $scenario)->assertSuccessful();

    expect($response->json('action.verb'))->toBe('invalid')
        ->and($response->json('action.reason'))->toContain($reason);
})->with([
    'unknown place' => ['Let us go to the moon. [action: go_to moon]', '"moon"'],
    'unknown verb' => ['[action: dance wildly]', '"dance"'],
    'missing target' => ['[action: go_to]', 'needs a place'],
    'unknown activity at a spot' => ['[action: use pool-lounger-1-seat backflip]', '"backflip"'],
]);

it('returns no action when the reply has none', function () {
    $scenario = worldStateScenario();
    replyWith('Just chatting.');

    sendResidentMessage($this, $scenario)->assertSuccessful()->assertJsonPath('action', null);
});

it('returns no action outside a world', function () {
    [$user, $assistant, $conversation] = setUpAgentAssistant('assistant');
    replyWith('On my way! [action: go_to pool-terrace]');

    $this->actingAs($user)->postJson(route('conversations.sendMessage', ['assistant' => $assistant->id, 'id' => $conversation->id]), [
        'messages' => [['role' => 'user', 'content' => 'Hello']],
    ])->assertSuccessful()->assertJsonPath('action', null);
});

it('tells the resident which action tags she can use', function () {
    $scenario = worldStateScenario();

    sendResidentMessage($this, $scenario)->assertSuccessful();

    expect(sentSystemPrompt())
        ->toContain('[action: go_to <place or thing id>]')
        ->toContain('[action: follow]')
        ->toContain('[action: stop]');
});
