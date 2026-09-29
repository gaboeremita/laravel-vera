<?php

use App\Actions\BuildQuestsPrompt;
use App\Actions\Quests\RecordQuestEvent;
use App\Actions\Quests\SyncSessionQuests;
use App\Enums\EndingStatus;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Enums\TurnMode;
use App\Jobs\AssessQuestEnding;
use App\Models\AiModel;
use App\Models\Campaign;
use App\Models\QuestEvent;
use App\Models\WorldSession;
use App\Models\WorldSessionCampaign;
use App\Models\WorldSessionQuest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function endingResponse(array $overrides = []): array
{
    return toolCallResponse('ending_1', 'record_ending', [
        'tier' => 'vindicated',
        'title' => 'The Miller Cleared',
        'epilogue' => 'The village believed you, and the mill turned again.',
        'scores' => [['dimension' => 'honesty', 'score' => 8, 'reason' => 'You told the truth.']],
        'resultingFlags' => [],
        ...$overrides,
    ]);
}

/**
 * A completed run of a quest with tiers, whose resident talked with the player during it.
 */
function endedRunScenario(array $rubric = []): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, $region, $resident, $session] = $scenario;
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    $quest = worldQuest($region->world, [
        'start' => ['mode' => 'offer', 'giver' => $resident->id],
        'rubric' => ['guidance' => 'Judge how honest the player was.', 'dimensions' => [['name' => 'honesty', 'description' => 'Did they tell the truth?']], 'tiers' => ['vindicated', 'scapegoated'], ...$rubric],
    ]);
    $run = WorldSessionQuest::factory()->ended()->create(['world_session_id' => $session->id, 'quest_id' => $quest->id, 'started_at' => now()->subHour(), 'ended_at' => now()->addMinute()]);
    app(RecordQuestEvent::class)->handle($run, QuestEventType::BeatFinished, 'first', ['trigger' => 'PlayerTalkedTo']);
    app(RecordQuestEvent::class)->handle($run, QuestEventType::FlagSet, 'first', ['flag' => 'firstDone'], byCreator: true);
    $conversation->update(['world_session_id' => $session->id]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'I saw the sluice break. [OOC: make them believe me]']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Then it was never the miller.']);

    return [...$scenario, $run];
}

it('writes the ending from the rubric, the log with creator steps marked, and conversations without OOC text', function () {
    [, , , , , , $run] = endedRunScenario();
    fakeTurn(endingResponse());

    AssessQuestEnding::dispatchSync($run->id);

    $request = Http::recorded()[0][0];
    $sent = collect($request['messages'])->pluck('content')->implode("\n");
    $parameters = $request['tools'][0]['function']['parameters'];
    expect($sent)->toContain('Judge how honest the player was.')->toContain('(done through creator mode)')->toContain('I saw the sluice break.')->not->toContain('make them believe me')
        ->and($parameters['properties']['tier']['enum'])->toBe(['vindicated', 'scapegoated'])
        ->and($parameters['properties']['scores']['items']['properties']['dimension']['enum'])->toBe(['honesty'])
        ->and($parameters['properties']['epilogue']['description'])->toBe('One short paragraph of two to four sentences telling how it ended, addressed to the user.')
        ->and($run->fresh())->ending_status->toBe(EndingStatus::Written)->ending->toMatchArray(['tier' => 'vindicated', 'title' => 'The Miller Cleared'])
        ->and(QuestEvent::where('type', QuestEventType::EndingWritten)->exists())->toBeTrue();
});

it('lets the ending describe itself freely when the author gave no tiers', function () {
    [, , , , , , $run] = endedRunScenario(['tiers' => []]);
    fakeTurn(endingResponse(['tier' => 'whatever']));

    AssessQuestEnding::dispatchSync($run->id);

    expect(Http::recorded()[0][0]['tools'][0]['function']['parameters']['properties'])->not->toHaveKey('tier')
        ->and($run->fresh()->ending['tier'])->toBeNull();
});

it('records a failed ending and writes it again on request', function () {
    [$user, , , $region, , $session, $run] = endedRunScenario();

    (new AssessQuestEnding($run->id))->failed(new RuntimeException('The model was down.'));
    expect($run->fresh()->ending_status)->toBe(EndingStatus::Failed)
        ->and(QuestEvent::where('type', QuestEventType::EndingFailed)->first()->payload['error'])->toBe('The model was down.');

    fakeTurn(endingResponse());
    $this->actingAs($user)->postJson(route('worlds.sessions.quest-runs.assess', [$region->world_id, $session->id, $run->id]))->assertAccepted();

    expect($run->fresh()->ending_status)->toBe(EndingStatus::Written);
});

it('ends and writes the ending of a quest the player abandons', function () {
    [$user, , , $region, , $session] = worldStateScenario(fakeReply: false);
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    $quest = worldQuest($region->world, ['rubric' => ['guidance' => '', 'dimensions' => [['name' => 'honesty']], 'tiers' => []]]);
    $run = WorldSessionQuest::factory()->active()->create(['world_session_id' => $session->id, 'quest_id' => $quest->id]);
    fakeTurn(endingResponse());

    $this->actingAs($user)->postJson(route('worlds.sessions.quest-runs.abandon', [$region->world_id, $session->id, $run->id]))
        ->assertOk()->assertJsonPath('status', 'abandoned');

    expect($run->fresh())->status->toBe(QuestStatus::Abandoned)->ending_status->toBe(EndingStatus::Written);
});

it('lets involved residents remember an ending in its own session only', function () {
    [, , , $region, $resident, $session, $run] = endedRunScenario();
    $run->update(['ending_status' => EndingStatus::Written, 'ending' => ['tier' => 'vindicated', 'title' => 'Cleared', 'epilogue' => 'The mill turned again.', 'scores' => [], 'resultingFlags' => []]]);
    $other = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $region->id]);

    expect(app(BuildQuestsPrompt::class)->handle($session, $resident, TurnMode::InCharacter))->toContain('The mill turned again.')
        ->and(app(BuildQuestsPrompt::class)->handle($other, $resident, TurnMode::InCharacter))->toBeNull();
});

it('writes a campaign\'s ending once, after its last quest ends, with a tier from its own list', function () {
    [, , , $region, , $session] = worldStateScenario(fakeReply: false);
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    $campaign = Campaign::factory()->create(['world_id' => $region->world_id, 'definition' => ['description' => 'The valley.', 'rubric' => ['guidance' => '', 'dimensions' => [['name' => 'resolve']], 'tiers' => ['saved', 'lost']]]]);
    $first = worldQuest($region->world, ['repeatable' => true], ['key' => 'first', 'campaign_id' => $campaign->id]);
    $second = worldQuest($region->world, [], ['key' => 'second', 'campaign_id' => $campaign->id]);
    WorldSessionQuest::factory()->withEnding()->create(['world_session_id' => $session->id, 'quest_id' => $first->id]);
    WorldSessionQuest::factory()->create(['world_session_id' => $session->id, 'quest_id' => $first->id, 'run' => 2]);
    $last = WorldSessionQuest::factory()->ended()->create(['world_session_id' => $session->id, 'quest_id' => $second->id]);
    fakeTurn(endingResponse(['tier' => null, 'scores' => []]), toolCallResponse('ending_2', 'record_ending', ['tier' => 'saved', 'title' => 'The Valley Saved', 'epilogue' => 'The river kept its banks.', 'scores' => [['dimension' => 'resolve', 'score' => 9, 'reason' => 'You saw it through.']], 'resultingFlags' => []]));

    AssessQuestEnding::dispatchSync($last->id);

    $ending = WorldSessionCampaign::sole();
    expect($ending->ending_status)->toBe(EndingStatus::Written)
        ->and($ending->ending['tier'])->toBe('saved')
        ->and(Http::recorded()[1][0]['tools'][0]['function']['parameters']['properties']['tier']['enum'])->toBe(['saved', 'lost']);
});

it('lets a later quest require a campaign\'s outcome', function () {
    [, , , $region, , $session] = worldStateScenario();
    $campaign = Campaign::factory()->create(['world_id' => $region->world_id, 'key' => 'the-river']);
    worldQuest($region->world, ['requires' => [['campaign' => 'the-river', 'outcome' => 'tier:saved']]], ['key' => 'after']);

    app(SyncSessionQuests::class)->handle($session->fresh());
    expect($session->questRuns()->count())->toBe(0);

    WorldSessionCampaign::factory()->create(['world_session_id' => $session->id, 'campaign_id' => $campaign->id, 'ending_status' => EndingStatus::Written, 'ending' => ['tier' => 'saved', 'title' => 'Saved', 'epilogue' => 'Saved.', 'scores' => [], 'resultingFlags' => []]]);
    app(SyncSessionQuests::class)->handle($session->fresh());

    expect($session->questRuns()->count())->toBe(1);
});
