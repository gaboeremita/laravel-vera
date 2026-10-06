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

it('accepts offer conditions with every new part, naming this quest or another', function () {
    $scenario = worldStateScenario();
    [, , , $region, $resident] = $scenario;
    worldSentiments($region->world);
    $item = worldItem($region);
    worldQuest($region->world, [], ['key' => 'the-ledger']);

    $result = checkQuest($scenario, [
        'start' => ['mode' => 'offer', 'giver' => $resident->id, 'offerQuestion' => 'Has the user shown they can keep a secret?', 'offerWhen' => ['all' => [
            ['sentiment' => ['resident' => $resident->id, 'kind' => 'trust', 'atLeast' => 3, 'atMost' => 8.5]],
            ['questState' => ['quest' => 'the-quest', 'state' => 'declined']],
            ['declinedTimes' => ['quest' => 'the-ledger', 'atLeast' => 2]],
            ['gaveTo' => ['resident' => $resident->id, 'item' => $item->id, 'atLeast' => 1]],
            ['spentWith' => ['resident' => $resident->id, 'atLeast' => 10]],
            ['messagesWith' => ['resident' => $resident->id, 'atLeast' => 5]],
            ['giverIn' => ['region' => $region->id, 'zone' => 'studio']],
            ['any' => [['othersInTheZone' => ['nobody' => true]], ['othersInTheZone' => ['resident' => $resident->id]]]],
            ['enterRegion' => $region->id],
        ]]],
        'beats' => [QuestFactory::beat('go', ['when' => ['sentiment' => ['resident' => $resident->id, 'kind' => 'liking', 'atMost' => -2]]])],
    ]);

    expect($result['errors'])->toBe([]);
});

it('refuses the new parts with unknown references, out of range or without a bound', function () {
    $scenario = worldStateScenario();
    [, , , $region, $resident] = $scenario;
    worldSentiments($region->world);

    $result = checkQuest($scenario, ['beats' => [QuestFactory::beat('go', ['when' => ['all' => [
        ['sentiment' => ['resident' => 999, 'kind' => 'awe', 'atLeast' => 12]],
        ['sentiment' => ['resident' => $resident->id, 'kind' => 'trust']],
        ['sentiment' => ['resident' => $resident->id, 'kind' => 'trust', 'atLeast' => 5, 'atMost' => 2]],
        ['questState' => ['quest' => 'nowhere', 'state' => 'lost']],
        ['declinedTimes' => ['quest' => 'the-quest', 'atLeast' => 0]],
        ['gaveTo' => ['resident' => $resident->id, 'item' => 999, 'atLeast' => 1]],
        ['spentWith' => ['resident' => $resident->id, 'atLeast' => 0]],
    ]]])]]);

    expect(array_keys($result['errors']))->toEqual([
        'beats.0.when.all.0.sentiment.resident',
        'beats.0.when.all.0.sentiment.kind',
        'beats.0.when.all.0.sentiment.atLeast',
        'beats.0.when.all.1.sentiment',
        'beats.0.when.all.2.sentiment.atMost',
        'beats.0.when.all.3.questState.quest',
        'beats.0.when.all.3.questState.state',
        'beats.0.when.all.4.declinedTimes.atLeast',
        'beats.0.when.all.5.gaveTo.item',
        'beats.0.when.all.6.spentWith.atLeast',
    ]);
});

it('refuses the moment parts outside Offer when, and beats and questions inside it', function () {
    $scenario = worldStateScenario();
    [, , , $region, $resident] = $scenario;
    $messages = ['messagesWith' => ['resident' => $resident->id, 'atLeast' => 1]];

    $result = checkQuest($scenario, [
        'start' => ['mode' => 'offer', 'giver' => $resident->id, 'offerWhen' => ['any' => [['beat' => 'go'], ['question' => 'sorry']]]],
        'beats' => [QuestFactory::beat('go', ['when' => $messages])],
        'complete' => ['giverIn' => ['region' => $region->id, 'zone' => 'studio']],
        'fail' => ['othersInTheZone' => ['nobody' => true]],
    ]);

    expect($result['errors'])->toMatchArray([
        'start.offerWhen.any.0' => ['Offer when can\'t depend on this quest\'s own beats or questions.'],
        'start.offerWhen.any.1' => ['Offer when can\'t depend on this quest\'s own beats or questions.'],
        'beats.0.when' => ['This condition only works in Offer when.'],
        'complete' => ['This condition only works in Offer when.'],
        'fail' => ['This condition only works in Offer when.'],
    ]);

    $startWhen = checkQuest($scenario, ['start' => ['mode' => 'condition', 'when' => $messages]]);
    expect($startWhen['errors'])->toHaveKey('start.when');
});

it('refuses offer conditions on a quest that doesn\'t start by offer', function () {
    $scenario = worldStateScenario();
    [, , , , $resident] = $scenario;

    $result = checkQuest($scenario, ['start' => ['mode' => 'auto', 'offerWhen' => ['flag' => 'ready'], 'offerQuestion' => 'Ready?']]);
    $blank = checkQuest($scenario, ['start' => ['mode' => 'offer', 'giver' => $resident->id, 'offerQuestion' => '  ']]);

    expect($result['errors'])->toHaveKeys(['start.offerWhen', 'start.offerQuestion'])
        ->and($blank['errors'])->toHaveKey('start.offerQuestion');
});

it('warns when the giver of a quest with offer conditions cannot call tools', function () {
    $scenario = worldStateScenario();
    [, , , , $resident] = $scenario;
    AiModel::query()->update(['supports_tools' => false]);

    $result = checkQuest($scenario, ['start' => ['mode' => 'offer', 'giver' => $resident->id, 'offerQuestion' => 'Ready?']]);

    expect($result['errors'])->toBe([])
        ->and($result['warnings'])->toContain("{$resident->assistant->name}'s model can't call tools, so they can't check what the quest asks before offering it.");
});
