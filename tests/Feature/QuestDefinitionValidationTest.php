<?php

use App\Actions\Quests\ValidateQuestDefinition;
use App\Models\AiModel;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $changes
 * @return array{errors: array<string, array<int, string>>, warnings: array<int, string>}
 */
function checkQuest(array $scenario, array $changes, string $key = 'the-quest'): array
{
    [$user, , , $region] = $scenario;

    return app(ValidateQuestDefinition::class)->handle($region->world, $key, [...QuestFactory::defaultDefinition(), ...$changes], user: $user);
}

it('accepts a quest whose every reference exists in the world', function () {
    $scenario = worldStateScenario();
    [, , , $region, $resident] = $scenario;
    $item = worldItem($region);
    $fact = worldFact($resident);

    $result = checkQuest($scenario, [
        'start' => ['mode' => 'offer', 'giver' => $resident->id],
        'beats' => [
            QuestFactory::beat('arrive', ['when' => ['all' => [
                ['enterRegion' => $region->id],
                ['enterZone' => ['region' => $region->id, 'zone' => 'studio']],
                ['use' => ['region' => $region->id, 'object' => 'pool-lounger-1', 'activity' => 'recline']],
            ]]]),
            QuestFactory::beat('trade', [
                'requires' => ['arrive'],
                'when' => ['any' => [
                    ['has' => ['item' => $item->id, 'atLeast' => 2]],
                    ['credits' => ['atLeast' => 50]],
                    ['not' => ['knows' => $fact->id]],
                    ['acknowledged' => ['fact' => $fact->id, 'resident' => $resident->id]],
                    ['flag' => 'trusted'],
                    ['question' => 'sorry'],
                ]],
                'grants' => [['resident' => $resident->id, 'flag' => 'trusted']],
                'questions' => [['id' => 'sorry', 'text' => 'Has the player apologised?', 'residents' => [$resident->id]]],
                'knowledge' => [['resident' => $resident->id, 'prose' => 'You are wary of strangers.']],
            ]),
        ],
        'complete' => ['beat' => 'trade'],
    ]);

    expect($result['errors'])->toBe([])->and($result['warnings'])->toBe([]);
});

it('reports every missing reference at its path', function () {
    $scenario = worldStateScenario();
    [, , , $region] = $scenario;

    $result = checkQuest($scenario, ['beats' => [QuestFactory::beat('go', ['when' => ['all' => [
        ['enterRegion' => 999],
        ['enterZone' => ['region' => $region->id, 'zone' => 'cellar']],
        ['talkTo' => 999],
        ['has' => ['item' => 999, 'atLeast' => 1]],
        ['knows' => 999],
        ['use' => ['region' => $region->id, 'object' => 'pool-lounger-1', 'activity' => 'dance']],
        ['beat' => 'nowhere'],
    ]]])]]);

    expect(array_keys($result['errors']))->toEqual([
        'beats.0.when.all.0.enterRegion',
        'beats.0.when.all.1.enterZone.zone',
        'beats.0.when.all.2.talkTo',
        'beats.0.when.all.3.has.item',
        'beats.0.when.all.4.knows',
        'beats.0.when.all.5.use.activity',
        'beats.0.when.all.6.beat',
    ]);
});

it('names the beats of a cycle', function () {
    $result = checkQuest(worldStateScenario(), ['beats' => [
        QuestFactory::beat('a', ['requires' => ['c']]),
        QuestFactory::beat('b', ['requires' => ['a']]),
        QuestFactory::beat('c', ['requires' => ['b']]),
    ]]);

    expect($result['errors']['beats'][0])->toContain('a → c → b → a');
});

it('names the quests of a cycle across quests', function () {
    $scenario = worldStateScenario();
    [, , , $region] = $scenario;
    worldQuest($region->world, ['requires' => [['quest' => 'the-quest', 'outcome' => 'completed']]], ['key' => 'first']);

    $result = checkQuest($scenario, ['requires' => [['quest' => 'first', 'outcome' => 'completed']]]);

    expect($result['errors']['requires'][0])->toContain('the-quest → first → the-quest');
});

it('refuses duplicate ids, unknown start modes and outcomes, and a tier the required quest lacks', function () {
    $scenario = worldStateScenario();
    [, , , $region] = $scenario;
    worldQuest($region->world, ['rubric' => [...QuestFactory::defaultDefinition()['rubric'], 'tiers' => ['triumph']]], ['key' => 'first']);

    $result = checkQuest($scenario, [
        'start' => ['mode' => 'whenever'],
        'requires' => [['quest' => 'first', 'outcome' => 'tier:ruin'], ['quest' => 'first', 'outcome' => 'someday']],
        'beats' => [QuestFactory::beat('same'), QuestFactory::beat('same')],
    ]);

    expect($result['errors'])->toHaveKeys(['start.mode', 'requires.0.outcome', 'requires.1.outcome', 'beats.1.id']);
});

it('warns, without refusing, when a resident who grants flags cannot call tools', function () {
    $scenario = worldStateScenario();
    [, , , , $resident] = $scenario;
    AiModel::query()->update(['supports_tools' => false]);

    $result = checkQuest($scenario, ['beats' => [QuestFactory::beat('earn', [
        'when' => ['flag' => 'trusted'],
        'grants' => [['resident' => $resident->id, 'flag' => 'trusted']],
    ])]]);

    expect($result['errors'])->toBe([])
        ->and($result['warnings'][0])->toContain("model can't call tools");
});
