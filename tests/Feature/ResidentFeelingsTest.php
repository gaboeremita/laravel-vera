<?php

use App\Actions\BuildFeelingsPrompt;
use App\Enums\TurnMode;
use App\Models\ResidentFeeling;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Services\AgentLoop\Tools\World\AdjustFeelingsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function feelingsPositions(WorldResident $resident): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
}

function feelingsPromptSent(int $index = 0): string
{
    return collect(Http::recorded()[$index][0]['messages'])->firstWhere('role', 'system')['content'] ?? '';
}

it('tells the resident how they feel about the user, starting neutral, and lets them change it in character', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , $resident, $session] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(toolCallResponse('feel_1', 'adjust_feelings', ['liking' => 2, 'trust' => 1.5, 'reason' => 'They were kind to me.']), finalAnswerResponse('You are sweet.'));

    sendWorldMessage($this, $scenario, feelingsPositions($resident))->assertOk();

    expect(feelingsPromptSent())->toContain('Everyone starts at 0, a neutral feeling either way.')
        ->toContain('- Trust: -10 is expecting betrayal from them at every turn; 10 is trusting them with your life and your secrets.')
        ->toContain('Right now: romance 0, trust 0, liking 0. Read each value against its scale')
        ->toContain('Keep the numbers to yourself')
        ->toContain('call adjust_feelings in that same reply')
        ->and(collect(Http::recorded()[0][0]['tools'])->pluck('function.name'))->toContain('adjust_feelings')
        ->and(ResidentFeeling::of($session, $resident)->values())->toBe(['romance' => 0.0, 'trust' => 1.5, 'liking' => 2.0]);
});

it('lets the user read and set the numbers out of character', function () {
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, , $resident, $session] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    fakeTurn(toolCallResponse('feel_1', 'adjust_feelings', ['romance' => 8, 'reason' => 'The user asked for romance 8.']), finalAnswerResponse('Done.'));

    sendWorldMessage($this, $scenario, feelingsPositions($resident), ['messages' => [['role' => 'user', 'content' => '[OOC: set your romance to 8]']]])->assertOk();

    expect(feelingsPromptSent())->toContain('tell them the numbers plainly')->not->toContain('Keep the numbers to yourself')
        ->and(ResidentFeeling::of($session, $resident)->romance)->toBe(8.0);
});

it('moves a feeling at most 3 points at a time in character, and never past 10 either way', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);
    $feeling = ResidentFeeling::of($session, $resident);
    $feeling->update(['trust' => 9, 'liking' => -9]);

    (new AdjustFeelingsTool($feeling, TurnMode::InCharacter))->handle(['romance' => 7, 'trust' => 3, 'liking' => -3, 'reason' => 'A lot happened.']);

    expect($feeling->fresh()->values())->toBe(['romance' => 3.0, 'trust' => 10.0, 'liking' => -10.0])
        ->and((new AdjustFeelingsTool($feeling, TurnMode::InCharacter))->handle(['reason' => 'Nothing.'])['status'])->toBe('unchanged');
});

it('keeps feelings to their session', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);
    $other = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $session->region_id]);
    ResidentFeeling::of($session, $resident)->adjust(['liking' => 5]);

    expect(ResidentFeeling::of($other, $resident)->liking)->toBe(0.0);
});

it('leaves feelings out between residents', function () {
    [, , , , $resident, $session] = worldStateScenario(fakeReply: false);

    expect(app(BuildFeelingsPrompt::class)->handle(ResidentFeeling::of($session, $resident), TurnMode::BetweenResidents))->toBeNull();
});
