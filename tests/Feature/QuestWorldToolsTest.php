<?php

use App\Actions\BuildQuestsPrompt;
use App\Actions\Quests\SyncSessionQuests;
use App\Enums\QuestEventType;
use App\Enums\TurnMode;
use App\Models\QuestEvent;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Services\AgentLoop\Tools\World\GrantFlagTool;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function questChatPositions(WorldResident $resident): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
}

function questPromptOfRequest(int $index = 0): string
{
    return collect(Http::recorded()[$index][0]['messages'])->firstWhere('role', 'system')['content'] ?? '';
}

/**
 * @return array<int, string>
 */
function questToolsOfRequest(int $index = 0): array
{
    return collect(Http::recorded()[$index][0]['tools'] ?? [])->pluck('function.name')->all();
}

/**
 * A session with a quest whose first beat gives the resident prose and lets them grant "trusted".
 */
function trustScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, , , $region, $resident, $session] = $scenario;
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('earn', [
            'when' => ['flag' => 'trusted'],
            'knowledge' => [['resident' => $resident->id, 'prose' => 'You trust only those who helped at the well.']],
            'grants' => [['resident' => $resident->id, 'flag' => 'trusted']],
        ]),
        QuestFactory::beat('after', ['requires' => ['earn']]),
    ]]);
    app(SyncSessionQuests::class)->handle($session->fresh());

    return $scenario;
}

it('gives a resident the beat\'s prose while it is current, and not after', function () {
    $scenario = trustScenario();
    [, , , , $resident, $session] = $scenario;
    fakeTurn(finalAnswerResponse('Hello.'), finalAnswerResponse('Hello again.'));

    sendWorldMessage($this, $scenario, questChatPositions($resident))->assertOk();
    expect(questPromptOfRequest())->toContain('You trust only those who helped at the well.')
        ->toContain('The moment they truly have, call the grant_flag tool in that same reply, alongside your words, with your reason:')
        ->and(questToolsOfRequest())->toContain('grant_flag');

    $run = $session->questRuns()->first();
    $run->mergeState(['finishedBeats' => ['earn']]);
    $run->save();
    sendWorldMessage($this, $scenario, questChatPositions($resident))->assertOk();

    expect(questPromptOfRequest(1))->not->toContain('You trust only those who helped at the well.')
        ->and(questToolsOfRequest(1))->not->toContain('grant_flag');
});

it('finishes the beat when the resident grants its flag in character, logging why', function () {
    $scenario = trustScenario();
    [, , , , $resident, $session] = $scenario;
    fakeTurn(toolCallResponse('call_1', 'grant_flag', ['flag' => 'trusted', 'reason' => 'They carried water to the well all morning.']), finalAnswerResponse('I trust you now.'));

    sendWorldMessage($this, $scenario, questChatPositions($resident))->assertOk();

    $run = $session->questRuns()->first();
    expect($run->finishedBeats())->toBe(['earn'])
        ->and($run->state['flags']['trusted'])->toMatchArray(['by' => $resident->id, 'reason' => 'They carried water to the well all morning.'])
        ->and(QuestEvent::where('type', QuestEventType::FlagSet)->first()->payload['residentId'])->toBe($resident->id);
});

it('refuses a flag the beat does not let this resident grant, or a beat that is not current', function () {
    [, , , $region, $resident, $session] = worldStateScenario();
    $other = WorldResident::factory()->create(['region_id' => $region->id]);
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('first', ['grants' => [['resident' => $other->id, 'flag' => 'theirs']]]),
        QuestFactory::beat('later', ['requires' => ['first'], 'grants' => [['resident' => $resident->id, 'flag' => 'mine']]]),
    ]]);
    app(SyncSessionQuests::class)->handle($session->fresh());

    $tool = new GrantFlagTool($session, $resident);

    expect($tool->grantable())->toBeEmpty()
        ->and(fn () => $tool->handle(['flag' => 'theirs', 'reason' => 'x']))->toThrow(RuntimeException::class)
        ->and(fn () => $tool->handle(['flag' => 'mine', 'reason' => 'x']))->toThrow(RuntimeException::class);
});

it('gives residents on their own or talking with each other the prose, and nothing to grant', function () {
    [, , , , $resident, $session] = trustScenario();

    $prompt = app(BuildQuestsPrompt::class)->handle(WorldSession::find($session->id), $resident, TurnMode::BetweenResidents);

    expect($prompt)->toContain('You trust only those who helped at the well.')->not->toContain('grant_flag');
});
