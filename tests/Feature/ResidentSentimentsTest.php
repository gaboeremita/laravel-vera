<?php

use App\Actions\BuildSentimentsPrompt;
use App\Enums\TurnMode;
use App\Models\ResidentSentiment;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Services\AgentLoop\Tools\World\AdjustSentimentsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function sentimentsPositions(WorldResident $resident): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
}

it('tells the resident how they feel about the user in each of the world\'s sentiments, starting neutral, and lets them change it in character', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , $resident, $session] = $scenario;
    $resident->world->update(['sentiments' => [
        ['name' => 'awe', 'description' => '-10 is finding them utterly ordinary; 10 is being dazzled by them.'],
        ['name' => 'debt', 'description' => '-10 is them owing you everything; 10 is you owing them everything.'],
    ]]);
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(toolCallResponse('feel_1', 'adjust_sentiments', ['awe' => 2, 'debt' => 1.5, 'reason' => 'They saved me.']), finalAnswerResponse('You are amazing.'));

    sendWorldMessage($this, $scenario, sentimentsPositions($resident))->assertOk();

    $tools = collect(Http::recorded()[0][0]['tools']);
    expect(promptOfRequest())->toContain('Everyone starts at 0, a neutral feeling either way.')
        ->toContain('- awe: -10 is finding them utterly ordinary; 10 is being dazzled by them.')
        ->toContain('- debt: -10 is them owing you everything; 10 is you owing them everything.')
        ->toContain('Right now: awe 0, debt 0. Read each value against its scale')
        ->toContain('Keep the numbers to yourself')
        ->toContain('call adjust_sentiments in that same reply')
        ->and(array_keys($tools->firstWhere('function.name', 'adjust_sentiments')['function']['parameters']['properties']))->toBe(['awe', 'debt', 'reason'])
        ->and(ResidentSentiment::of($session, $resident)->scores())->toBe(['awe' => 2.0, 'debt' => 1.5]);
});

it('lets the user read and set the numbers out of character', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , $resident, $session] = $scenario;
    worldSentiments($resident->world);
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(toolCallResponse('feel_1', 'adjust_sentiments', ['romance' => 8, 'reason' => 'The user asked for romance 8.']), finalAnswerResponse('Done.'));

    sendWorldMessage($this, $scenario, sentimentsPositions($resident), ['message' => ['content' => '[OOC: set your romance to 8]']])->assertOk();

    expect(promptOfRequest())->toContain('tell them the numbers plainly')->not->toContain('Keep the numbers to yourself')
        ->and(ResidentSentiment::of($session, $resident)->scores()['romance'])->toBe(8.0);
});

it('gives a world with no sentiments no sentiments prompt and no tool to change them', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , $resident, $session] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(finalAnswerResponse('Hello.'));

    sendWorldMessage($this, $scenario, sentimentsPositions($resident))->assertOk();

    expect(promptOfRequest())->not->toContain('How you feel about the user right now')
        ->and(collect(Http::recorded()[0][0]['tools'])->pluck('function.name'))->not->toContain('adjust_sentiments');
});

it('moves a sentiment at most 3 points at a time in character, never past 10 either way, and ignores names the world lacks', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);
    worldSentiments($resident->world);
    $sentiment = ResidentSentiment::of($session, $resident);
    $sentiment->update(['values' => ['trust' => 9, 'liking' => -9]]);

    (new AdjustSentimentsTool($sentiment, TurnMode::InCharacter))->handle(['romance' => 7, 'trust' => 3, 'liking' => -3, 'awe' => 2, 'reason' => 'A lot happened.']);

    expect($sentiment->fresh()->scores())->toBe(['romance' => 3.0, 'trust' => 10.0, 'liking' => -10.0])
        ->and($sentiment->fresh()->values)->not->toHaveKey('awe')
        ->and((new AdjustSentimentsTool($sentiment, TurnMode::InCharacter))->handle(['reason' => 'Nothing.'])['status'])->toBe('unchanged');
});

it('keeps sentiments to their session', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);
    worldSentiments($resident->world);
    $other = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $session->region_id]);
    ResidentSentiment::of($session, $resident)->adjust(['liking' => 5]);

    expect(ResidentSentiment::of($other, $resident)->scores()['liking'])->toBe(0.0);
});

it('leaves sentiments out between residents', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);
    worldSentiments($resident->world);

    expect(app(BuildSentimentsPrompt::class)->handle(ResidentSentiment::of($session, $resident), TurnMode::BetweenResidents))->toBeNull();
});
