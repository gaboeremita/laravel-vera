<?php

use App\Actions\BuildQuestsPrompt;
use App\Actions\Quests\LookUpOfferCondition;
use App\Actions\Quests\OfferMoment;
use App\Actions\Quests\OfferQuestionStatus;
use App\Actions\Quests\QuestSessionState;
use App\Actions\Quests\RecordQuestEvent;
use App\Actions\Quests\SyncSessionQuests;
use App\Actions\ResolveInventory;
use App\Actions\TransferInventory;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Enums\TurnMode;
use App\Events\Quests\PlayerEnteredRegion;
use App\Models\Conversation;
use App\Models\Quest;
use App\Models\QuestEvent;
use App\Models\QuestOffer;
use App\Models\ResidentSentiment;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use App\Services\AgentLoop\Tools\World\CheckOfferConditionTool;
use App\Services\AgentLoop\Tools\World\OfferQuestTool;
use App\Services\AgentLoop\Tools\World\SignalQuestionTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A session whose resident gives "The Ledger", with what it asks before
 * being offered, built from the giver.
 *
 * @param  ?Closure(WorldResident): array<string, mixed>  $offerWhen
 */
function offerConditionScenario(?Closure $offerWhen, ?string $offerQuestion = null): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, , $conversation, $region, $resident, $session] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    $factory = Quest::factory()->offeredBy($resident);
    $factory = $offerWhen !== null ? $factory->offerWhen($offerWhen($resident)) : $factory;
    $factory = $offerQuestion !== null ? $factory->offerQuestion($offerQuestion) : $factory;
    $factory->create(['world_id' => $region->world_id, 'key' => 'the-ledger', 'title' => 'The Ledger']);
    app(SyncSessionQuests::class)->handle($session->fresh());

    return $scenario;
}

/**
 * The giver stands on the pool terrace; others are placed as given.
 *
 * @param  array<int, array{x: float, y: float, z: float}>  $others
 */
function giverPositions(WorldResident $resident, array $others = []): array
{
    return ['user' => ['x' => 8, 'y' => 0, 'z' => -8], 'residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3]] + $others];
}

function offerPrompt(int $index = 0): string
{
    return promptOfRequest($index);
}

/**
 * @return array<int, string>
 */
function offerTools(int $index = 0): array
{
    return collect(Http::recorded()[$index][0]['tools'] ?? [])->pluck('function.name')->all();
}

function trustAtLeast(int $value): Closure
{
    return fn (WorldResident $resident) => ['sentiment' => ['resident' => $resident->id, 'kind' => 'trust', 'atLeast' => $value]];
}

function ledgerRun(WorldSession $session): WorldSessionQuest
{
    return $session->questRuns()->with('quest')->latest('id')->firstOrFail();
}

function offerMomentOf(WorldSession $session, WorldResident $giver, array $positions): OfferMoment
{
    return new OfferMoment($session->fresh(), $giver, $giver->region, $positions);
}

function offerPromptFor(WorldSession $session, WorldResident $resident): string
{
    return (string) app(BuildQuestsPrompt::class)->handle($session->fresh(), $resident, TurnMode::InCharacter);
}

it('tells the giver what the quest asks in plain words, and gives them the check', function () {
    $scenario = offerConditionScenario(trustAtLeast(3));
    [, , , , $resident] = $scenario;
    fakeTurn(finalAnswerResponse('Hello.'));

    sendWorldMessage($this, $scenario, giverPositions($resident))->assertOk();

    expect(offerPrompt())->toContain('- The Ledger: Something needs doing. Offer it only once this holds: your trust toward the user is at least 3. Check each part with check_offer_condition before you offer.')
        ->toContain('Until you offer, you may hint in character that you have something in mind, if you judge it fits. Never name or describe the task, its conditions, or what you checked.')
        ->and(offerTools())->toContain('check_offer_condition', 'offer_quest');
});

it('leaves a quest that asks nothing before being offered as it was', function () {
    $scenario = offerConditionScenario(null);
    [, , , , $resident] = $scenario;
    fakeTurn(finalAnswerResponse('Hello.'));

    sendWorldMessage($this, $scenario, giverPositions($resident))->assertOk();

    expect(offerPrompt())->toContain('- The Ledger: Something needs doing.')
        ->not->toContain('Offer it only once')
        ->not->toContain('you may hint in character')
        ->and(offerTools())->toContain('offer_quest')->not->toContain('check_offer_condition');
});

it('answers a check with the value now and what the quest asks, and refuses a part the quest does not have', function () {
    $scenario = offerConditionScenario(trustAtLeast(3));
    [, , , , $resident, $session] = $scenario;
    worldSentiments($resident->world);
    ResidentSentiment::of($session, $resident)->adjust(['trust' => 2]);
    fakeTurn(
        toolCallResponse('check_1', 'check_offer_condition', ['quest' => 'The Ledger', 'part' => 'your trust toward the user is at least 3']),
        toolCallResponse('check_2', 'check_offer_condition', ['quest' => 'The Ledger', 'part' => 'the moon is full']),
        finalAnswerResponse('Not yet.'),
    );

    sendWorldMessage($this, $scenario, giverPositions($resident))->assertOk();

    expect(toolResultSentBack(1))->toContain('"part":"your trust toward the user"')->toContain('"value":"2.0"')->toContain('"asks":"at least 3"')
        ->and(toolResultSentBack(2))->toContain('That isn\'t part of what this task asks. Its parts are: your trust toward the user is at least 3.');
});

it('lets the giver offer while the quest\'s condition doesn\'t hold, recording what they checked', function () {
    $scenario = offerConditionScenario(trustAtLeast(3));
    [, , , , $resident, $session] = $scenario;
    worldSentiments($resident->world);
    ResidentSentiment::of($session, $resident)->adjust(['trust' => 1]);
    fakeTurn(
        toolCallResponse('check_1', 'check_offer_condition', ['quest' => 'The Ledger', 'part' => 'your trust toward the user is at least 3']),
        toolCallResponse('offer_1', 'offer_quest', ['quest' => 'The Ledger']),
        finalAnswerResponse('Will you help me?'),
    );

    sendWorldMessage($this, $scenario, giverPositions($resident))->assertOk()->assertJsonPath('questOffer.questTitle', 'The Ledger');

    $offered = QuestEvent::where('type', QuestEventType::Offered)->firstOrFail();
    expect($offered->payload['lookups'])->toBe([['quest' => 'The Ledger', 'part' => 'your trust toward the user', 'value' => '1.0', 'asks' => 'at least 3']])
        ->and($offered->payload['offerWhenHeld'])->toBeFalse()
        ->and($offered->payload['unmetParts'])->toBe(['your trust toward the user is at least 3']);
});

it('records a met condition, and a latched part once it has happened', function () {
    [, , , $region, $resident, $session] = offerConditionScenario(fn (WorldResident $resident) => ['all' => [['enterRegion' => $resident->region_id], ['credits' => ['atLeast' => 5]]]]);
    app(ResolveInventory::class)->forPlayer($session)->update(['credits' => 10]);
    $moment = offerMomentOf($session, $resident, giverPositions($resident));
    $lookUp = app(LookUpOfferCondition::class);
    $state = fn () => QuestSessionState::for($session->fresh());

    expect($lookUp->lookUp(ledgerRun($session), "the user has entered {$region->name}", $state(), $moment)['value'])->toBe('hasn\'t happened')
        ->and($lookUp->holds(ledgerRun($session), $state(), $moment))->toBeFalse();

    PlayerEnteredRegion::dispatch($session->id, $region->id);

    expect($lookUp->lookUp(ledgerRun($session), "the user has entered {$region->name}", $state(), $moment)['value'])->toBe('has happened')
        ->and($lookUp->lookUp(ledgerRun($session), 'the user holds at least 5 credits', $state(), $moment)['value'])->toBe('10')
        ->and($lookUp->holds(ledgerRun($session), $state(), $moment))->toBeTrue();
});

it('leaves a pending offer alone: no condition line and no check for it', function () {
    [, , $conversation, , $resident, $session] = offerConditionScenario(trustAtLeast(3));
    QuestOffer::factory()->create(['world_session_id' => $session->id, 'world_session_quest_id' => ledgerRun($session)->id, 'conversation_id' => $conversation->id, 'world_resident_id' => $resident->id]);

    expect(offerPromptFor($session, $resident))->not->toContain('Offer it only once')
        ->and((new CheckOfferConditionTool(
            new OfferQuestTool($session, $conversation, $resident),
            offerMomentOf($session, $resident, giverPositions($resident)),
        ))->checkable())->toBeEmpty();
});

it('gives the checks the sentiment, a quest\'s state and the sums', function () {
    [, , , $region, $resident, $session, $player, $residentInventory] = inventoryScenario();
    $bread = worldItem($region, ['name' => 'bread']);
    $player->items()->create(['item_id' => $bread->id, 'quantity' => 3]);
    Quest::factory()->offeredBy($resident)->offerWhen(['all' => [
        ['gaveTo' => ['resident' => $resident->id, 'item' => $bread->id, 'atLeast' => 2]],
        ['spentWith' => ['resident' => $resident->id, 'atLeast' => 10]],
        ['questState' => ['quest' => 'the-ledger', 'state' => 'declined']],
    ]])->create(['world_id' => $region->world_id, 'key' => 'the-ledger', 'title' => 'The Ledger']);
    app(SyncSessionQuests::class)->handle($session->fresh());
    app(TransferInventory::class)->handle($player, $residentInventory, 7, [$bread->id => 1], 'gift');
    $moment = offerMomentOf($session, $resident, giverPositions($resident));
    $lookUp = fn (string $part) => app(LookUpOfferCondition::class)->lookUp(ledgerRun($session), $part, QuestSessionState::for($session->fresh()), $moment);

    expect($lookUp('the user has given you at least 2 bread'))->toBe(['part' => 'how many bread the user has given you', 'value' => '1', 'asks' => 'at least 2'])
        ->and($lookUp('the user has paid you at least 10 credits')['value'])->toBe('7')
        ->and($lookUp('"The Ledger" is declined')['value'])->toBe('available');
});

it('reads where the giver stands and who is with them', function () {
    [, , , $region, $resident, $session] = offerConditionScenario(fn (WorldResident $resident) => ['all' => [
        ['giverIn' => ['region' => $resident->region_id, 'zone' => 'studio']],
        ['othersInTheZone' => ['nobody' => true]],
    ]]);
    $other = WorldResident::factory()->create(['region_id' => $region->id]);
    $lookUp = app(LookUpOfferCondition::class);
    $state = QuestSessionState::for($session->fresh());
    $inBooth = [$resident->id => ['x' => -2, 'y' => 0, 'z' => 8]];

    $alone = offerMomentOf($session, $resident, ['residents' => $inBooth + [$other->id => ['x' => 5, 'y' => 0, 'z' => -3]]]);
    expect($lookUp->holds(ledgerRun($session), $state, $alone))->toBeTrue()
        ->and($lookUp->lookUp(ledgerRun($session), 'you are in Music studio in '.$region->name, $state, $alone)['value'])->toBe('Vocal booth in '.$region->name);

    $inTheStudioOutside = offerMomentOf($session, $resident, ['residents' => $inBooth + [$other->id => ['x' => -8, 'y' => 0, 'z' => 2]]]);
    expect($lookUp->holds(ledgerRun($session), $state, $inTheStudioOutside))->toBeTrue()
        ->and($lookUp->lookUp(ledgerRun($session), 'nobody else is in your zone', $state, $inTheStudioOutside)['value'])->toBe('nobody');

    $sharingTheBooth = offerMomentOf($session, $resident, ['residents' => $inBooth + [$other->id => ['x' => -1, 'y' => 0, 'z' => 9]]]);
    expect($lookUp->holds(ledgerRun($session), $state, $sharingTheBooth))->toBeFalse()
        ->and($lookUp->unmetParts(ledgerRun($session), $state, $sharingTheBooth))->toBe(['nobody else is in your zone'])
        ->and($lookUp->lookUp(ledgerRun($session), 'nobody else is in your zone', $state, $sharingTheBooth)['value'])->toBe($other->assistant->name);

    $unplaced = offerMomentOf($session, $resident, ['residents' => $inBooth]);
    expect($lookUp->holds(ledgerRun($session), $state, $unplaced))->toBeTrue();
});

it('names a named resident in the giver\'s zone, and says when a zone no longer exists', function () {
    [, , , $region, $resident, $session] = offerConditionScenario(null);
    $other = WorldResident::factory()->create(['region_id' => $region->id]);
    $quest = ledgerRun($session)->quest;
    $quest->update(['definition' => [...$quest->definition, 'start' => [...$quest->definition['start'], 'offerWhen' => ['all' => [
        ['othersInTheZone' => ['resident' => $other->id]],
        ['giverIn' => ['region' => $region->id, 'zone' => 'cellar']],
    ]]]]]);
    $state = QuestSessionState::for($session->fresh());
    $moment = offerMomentOf($session, $resident, ['residents' => [$resident->id => ['x' => 5, 'y' => 0, 'z' => -3], $other->id => ['x' => 6, 'y' => 0, 'z' => -4]]]);
    $lookUp = app(LookUpOfferCondition::class);

    expect($lookUp->unmetParts(ledgerRun($session), $state, $moment))->toBe(['you are in cellar in '.$region->name])
        ->and($lookUp->lookUp(ledgerRun($session), 'you are in cellar in '.$region->name, $state, $moment)['value'])->toBe("cellar no longer exists in {$region->name}")
        ->and($lookUp->lookUp(ledgerRun($session), "{$other->assistant->name} is in your zone", $state, $moment)['value'])->toBe($other->assistant->name);
});

it('counts every message the player sent the resident in the session, and none sent to others', function () {
    [$user, , $conversation, $region, $resident, $session] = offerConditionScenario(fn (WorldResident $resident) => ['messagesWith' => ['resident' => $resident->id, 'atLeast' => 3]]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hello.']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Hi.']);
    $conversation->messages()->create(['role' => 'user', 'content' => '[OOC: how are you?]']);
    $other = WorldResident::factory()->create(['region_id' => $region->id]);
    Conversation::factory()->create(['owner_type' => $user->getMorphClass(), 'owner_id' => $user->id, 'counterpart_type' => $other->assistant->getMorphClass(), 'counterpart_id' => $other->assistant_id, 'world_session_id' => $session->id])
        ->messages()->create(['role' => 'user', 'content' => 'Elsewhere.']);
    $lookUp = app(LookUpOfferCondition::class);
    $moment = offerMomentOf($session, $resident, giverPositions($resident));

    expect($lookUp->lookUp(ledgerRun($session), 'the user has sent you at least 3 messages', QuestSessionState::for($session->fresh()), $moment)['value'])->toBe('2')
        ->and($lookUp->holds(ledgerRun($session), QuestSessionState::for($session->fresh()), $moment))->toBeFalse();

    $conversation->messages()->create(['role' => 'user', 'content' => '[creator mode: go on]']);

    expect($lookUp->holds(ledgerRun($session), QuestSessionState::for($session->fresh()), $moment))->toBeTrue();
});

it('keeps the offer question with the giver until the judge confirms it, then says so, across runs', function () {
    [, , $conversation, , $resident, $session] = offerConditionScenario(null, 'Has the user shown they can keep a secret?');
    $signal = new SignalQuestionTool($session, $conversation, $resident);

    expect($signal->signallable()->keys()->all())->toBe(['Has the user shown they can keep a secret?'])
        ->and(offerPromptFor($session, $resident))->toContain('Also wait until you are sure of this: Has the user shown they can keep a secret? When you believe the user has shown it, call signal_question.')
        ->not->toContain('It has been confirmed.');

    $judged = fn (bool $met, string $reason) => app(RecordQuestEvent::class)->handle(ledgerRun($session), QuestEventType::QuestionJudged, payload: ['question' => Quest::OFFER_QUESTION_ID, 'met' => $met, 'messageIds' => [], 'reason' => $reason, ...OfferQuestionStatus::textHash(ledgerRun($session), Quest::OFFER_QUESTION_ID)]);
    $judged(false, 'They told everyone.');
    expect(offerPromptFor($session, $resident))->toContain('Not confirmed yet: They told everyone.');

    $judged(true, 'They kept it.');
    ledgerRun($session)->update(['status' => QuestStatus::Completed]);
    $session->questRuns()->create(['quest_id' => ledgerRun($session)->quest_id, 'run' => 2, 'status' => QuestStatus::Available, 'state' => WorldSessionQuest::EMPTY_STATE]);

    expect(offerPromptFor($session, $resident))->toContain('It has been confirmed.')
        ->and($signal->signallable())->toBeEmpty();
});
