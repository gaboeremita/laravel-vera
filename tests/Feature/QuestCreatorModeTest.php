<?php

use App\Actions\Quests\SyncSessionQuests;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Models\QuestEvent;
use App\Models\WorldSessionQuest;
use App\Services\AgentLoop\Tools\World\EditQuestTool;
use App\Services\AgentLoop\Tools\World\EndQuestTool;
use App\Services\AgentLoop\Tools\World\ResetQuestTool;
use App\Services\AgentLoop\Tools\World\SetBeatTool;
use App\Services\AgentLoop\Tools\World\SetQuestFlagTool;
use App\Services\AgentLoop\Tools\World\StartQuestTool;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A session playing "The Mill", three beats in a row.
 */
function creatorQuestScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, , , $region, , $session] = $scenario;
    worldQuest($region->world, ['beats' => [QuestFactory::beat('a'), QuestFactory::beat('b', ['requires' => ['a']]), QuestFactory::beat('c', ['requires' => ['b']]), QuestFactory::beat('d', ['requires' => ['c']])]], ['title' => 'The Mill']);
    app(SyncSessionQuests::class)->handle($session->fresh());

    return $scenario;
}

it('gives the character the quest tools only on creator turns', function () {
    $scenario = creatorQuestScenario();
    [, , $conversation, , $resident] = $scenario;
    $positions = ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
    fakeTurn(finalAnswerResponse('No.'), finalAnswerResponse('Done.'));

    sendWorldMessage($this, $scenario, $positions, ['message' => ['content' => '[creator mode: finish the first beat]']])->assertOk();
    $conversation->update(['creator_mode_at' => now()]);
    sendWorldMessage($this, $scenario, $positions, ['message' => ['content' => '[creator mode: finish the first beat]']])->assertOk();

    $tools = fn (int $index) => collect(Http::recorded()[$index][0]['tools'] ?? [])->pluck('function.name')->all();
    expect($tools(0))->not->toContain('set_beat')
        ->and($tools(1))->toContain('start_quest')->toContain('end_quest')->toContain('reset_quest')->toContain('set_beat')->toContain('set_quest_flag')->toContain('assess_quest')->toContain('edit_quest');
});

it('finishes a beat and undoes it with the beats after it, each logged as the creator\'s', function () {
    [, , , , , $session] = creatorQuestScenario();
    $tool = new SetBeatTool($session);

    $tool->handle(['quest' => 'The Mill', 'beat' => 'a', 'finished' => true]);
    $tool->handle(['quest' => 'The Mill', 'beat' => 'b', 'finished' => true]);
    $tool->handle(['quest' => 'The Mill', 'beat' => 'c', 'finished' => true]);
    $result = $tool->handle(['quest' => 'The Mill', 'beat' => 'a', 'finished' => false]);

    expect($result['undone'])->toEqualCanonicalizing(['a', 'b', 'c'])
        ->and($session->questRuns()->first()->finishedBeats())->toBe([])
        ->and(QuestEvent::where('type', QuestEventType::BeatUndone)->where('by_creator', true)->count())->toBe(3)
        ->and(QuestEvent::where('type', QuestEventType::BeatFinished)->where('by_creator', true)->count())->toBe(3);
});

it('resets a quest back to its start and keeps what came before in its log', function () {
    [, , , , , $session] = creatorQuestScenario();
    (new SetQuestFlagTool($session))->handle(['quest' => 'The Mill', 'flag' => 'aDone', 'on' => true]);
    $before = QuestEvent::count();

    (new ResetQuestTool($session))->handle(['quest' => 'The Mill']);

    $run = $session->questRuns()->first();
    expect($run->status)->toBe(QuestStatus::Active)
        ->and($run->state['flags'])->toBe([])
        ->and(QuestEvent::count())->toBeGreaterThan($before)
        ->and(QuestEvent::where('type', QuestEventType::Reset)->sole()->by_creator)->toBeTrue()
        ->and(QuestEvent::where('type', QuestEventType::FlagSet)->exists())->toBeTrue();
});

it('ends a quest, then starts a new run of it, as the creator', function () {
    [, , , , , $session] = creatorQuestScenario();

    (new EndQuestTool($session))->handle(['quest' => 'The Mill', 'outcome' => 'failed']);
    expect($session->questRuns()->first()->status)->toBe(QuestStatus::Failed)
        ->and(QuestEvent::where('type', QuestEventType::Failed)->sole()->by_creator)->toBeTrue();

    (new StartQuestTool($session))->handle(['quest' => 'The Mill']);
    expect($session->questRuns()->orderBy('run')->pluck('status')->all())->toEqual([QuestStatus::Failed, QuestStatus::Active]);
});

it('refuses an edit that names something not in the world, with the configuration\'s messages', function () {
    [, , , , , $session] = creatorQuestScenario();
    $quest = $session->questRuns()->first()->quest;

    expect(fn () => (new EditQuestTool($session))->handle(['quest' => 'The Mill', 'definition' => json_encode([...$quest->definition, 'beats' => [QuestFactory::beat('a', ['when' => ['enterRegion' => 999]])]])]))
        ->toThrow(RuntimeException::class, 'Choose a region of this world.');
    expect(WorldSessionQuest::first()->quest->beats())->toHaveCount(4);
});

it('refuses an edit that puts a moment of the conversation in a beat, as the configuration does', function () {
    [, , , , $resident, $session] = creatorQuestScenario();
    $quest = WorldSessionQuest::first()->quest;
    $beats = [QuestFactory::beat('a', ['when' => ['messagesWith' => ['resident' => $resident->id, 'atLeast' => 2]]])];

    expect(fn () => (new EditQuestTool($session))->handle(['quest' => 'The Mill', 'definition' => json_encode([...$quest->definition, 'beats' => $beats])]))
        ->toThrow(RuntimeException::class, 'This condition only works in Offer when.');
});
