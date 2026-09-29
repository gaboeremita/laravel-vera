<?php

use App\Actions\Quests\SyncSessionQuests;
use App\Enums\EndingStatus;
use App\Enums\QuestEventType;
use App\Enums\QuestOfferStatus;
use App\Enums\QuestStatus;
use App\Events\Quests\PlayerEnteredRegion;
use App\Models\QuestEvent;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use App\Services\AgentLoop\Tools\World\OfferQuestTool;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A session whose resident gives an available quest.
 */
function offerScenario(): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, $region, $resident, $session] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    worldQuest($region->world, ['start' => ['mode' => 'offer', 'giver' => $resident->id]], ['title' => 'The Flooded Mill']);
    app(SyncSessionQuests::class)->handle($session->fresh());

    return $scenario;
}

function offerPositions(WorldResident $resident): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]]];
}

it('lets only the giver offer an available quest', function () {
    [, , $conversation, $region, $resident, $session] = offerScenario();
    $stranger = WorldResident::factory()->create(['region_id' => $region->id]);

    expect((new OfferQuestTool($session, $conversation, $resident))->offerable()->keys()->all())->toBe(['The Flooded Mill'])
        ->and((new OfferQuestTool($session, $conversation, $stranger))->offerable())->toBeEmpty();

    $session->questRuns()->update(['status' => QuestStatus::Active]);
    expect((new OfferQuestTool($session, $conversation, $resident))->offerable())->toBeEmpty();
});

it('shows the offer from the conversation, and accepting starts the quest and tells the giver', function () {
    $scenario = offerScenario();
    [$user, , , $region, $resident, $session] = $scenario;
    fakeTurn(toolCallResponse('offer_1', 'offer_quest', ['quest' => 'The Flooded Mill']), finalAnswerResponse('Will you help me?'));

    $offer = sendWorldMessage($this, $scenario, offerPositions($resident))->assertOk()
        ->assertJsonPath('questOffer.questTitle', 'The Flooded Mill')
        ->json('questOffer');

    $this->actingAs($user)->postJson(route('worlds.sessions.quest-offers.answer', [$region->world_id, $session->id, $offer['id']]), ['accept' => true])
        ->assertOk()->assertJsonPath('line', '[You accept "The Flooded Mill"]')->assertJsonPath('run.status', 'active');

    expect($session->questRuns()->first()->status)->toBe(QuestStatus::Active);
});

it('keeps a declined quest available to be offered again', function () {
    [$user, , $conversation, $region, $resident, $session] = offerScenario();
    $tool = new OfferQuestTool($session, $conversation, $resident);
    $tool->handle(['quest' => 'The Flooded Mill']);

    $this->actingAs($user)->postJson(route('worlds.sessions.quest-offers.answer', [$region->world_id, $session->id, $tool->offer->id]), ['accept' => false])
        ->assertOk()->assertJsonPath('line', '[You decline "The Flooded Mill" for now]');
    $this->postJson(route('worlds.sessions.quest-offers.answer', [$region->world_id, $session->id, $tool->offer->id]), ['accept' => true])->assertConflict();

    expect($session->questRuns()->first()->status)->toBe(QuestStatus::Available)
        ->and((new OfferQuestTool($session, $conversation, $resident))->offerable())->not->toBeEmpty();
});

it('withdraws offers left unanswered when the chat closes or the session resumes', function () {
    [$user, , $conversation, $region, $resident, $session] = offerScenario();
    (new OfferQuestTool($session, $conversation, $resident))->handle(['quest' => 'The Flooded Mill']);

    $this->actingAs($user)->postJson(route('worlds.sessions.conversations.quest-offers.withdraw', [$region->world_id, $session->id, $conversation->id]))->assertNoContent();
    expect(QuestOffer::first()->status)->toBe(QuestOfferStatus::Withdrawn);

    (new OfferQuestTool($session, $conversation, $resident))->handle(['quest' => 'The Flooded Mill']);
    $this->postJson(route('worlds.sessions.resume', [$region->world_id, $session->id]))->assertOk();

    expect(QuestOffer::where('status', QuestOfferStatus::Pending)->count())->toBe(0)
        ->and(QuestEvent::where('type', QuestEventType::OfferWithdrawn)->count())->toBe(2);
});

it('makes a quest available only once the quest it requires ended in the tier it names', function () {
    [, , , $region, , $session] = worldStateScenario();
    $first = worldQuest($region->world, ['rubric' => [...QuestFactory::defaultDefinition()['rubric'], 'tiers' => ['triumph', 'ruin']]], ['key' => 'first']);
    worldQuest($region->world, ['start' => ['mode' => 'condition', 'when' => ['enterRegion' => $region->id]], 'requires' => [['quest' => 'first', 'outcome' => 'tier:triumph']]], ['key' => 'second']);
    $run = WorldSessionQuest::factory()->withEnding(['tier' => 'ruin'])->create(['world_session_id' => $session->id, 'quest_id' => $first->id]);

    app(SyncSessionQuests::class)->handle($session->fresh());
    expect($session->questRuns()->count())->toBe(1);

    $run->update(['ending' => [...$run->ending, 'tier' => 'triumph']]);
    app(SyncSessionQuests::class)->handle($session->fresh());
    expect($session->questRuns()->whereHas('quest', fn ($query) => $query->where('key', 'second'))->first()->status)->toBe(QuestStatus::Available);
});

it('gives a repeatable quest a new run that keeps the old one, and never another run to one that is not', function () {
    [, , , $region, , $session] = worldStateScenario();
    $again = worldQuest($region->world, ['start' => ['mode' => 'condition', 'when' => ['enterRegion' => $region->id]], 'repeatable' => true], ['key' => 'again']);
    $once = worldQuest($region->world, [], ['key' => 'once']);
    $old = WorldSessionQuest::factory()->withEnding()->create(['world_session_id' => $session->id, 'quest_id' => $again->id]);
    WorldSessionQuest::factory()->withEnding()->create(['world_session_id' => $session->id, 'quest_id' => $once->id]);

    app(SyncSessionQuests::class)->handle($session->fresh());

    expect($again->runs()->pluck('run')->all())->toBe([1, 2])
        ->and($old->fresh()->ending_status)->toBe(EndingStatus::Written)
        ->and($once->runs()->count())->toBe(1);

    PlayerEnteredRegion::dispatch($session->id, $region->id);
    expect($again->runs()->where('run', 2)->first()->status)->toBe(QuestStatus::Active);
});

it('keeps another user\'s session out of reach', function () {
    [, , $conversation, $region, $resident, $session] = offerScenario();
    $tool = new OfferQuestTool($session, $conversation, $resident);
    $tool->handle(['quest' => 'The Flooded Mill']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->postJson(route('worlds.sessions.quest-offers.answer', [$region->world_id, $session->id, $tool->offer->id]), ['accept' => true])->assertNotFound();
    expect(WorldSession::find($session->id)->questRuns()->first()->status)->toBe(QuestStatus::Available);
});
