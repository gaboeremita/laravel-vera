<?php

use App\Actions\Quests\QuestSessionState;
use App\Actions\Quests\SyncSessionQuests;
use App\Actions\Quests\WithdrawQuestOffers;
use App\Actions\RecordResidentActivity;
use App\Actions\TransferInventory;
use App\Enums\QuestEventType;
use App\Enums\QuestOfferStatus;
use App\Enums\QuestStatus;
use App\Events\Quests\FactAcknowledged;
use App\Events\Quests\PlayerEnteredRegion;
use App\Events\Quests\QuestStateChanged;
use App\Events\Quests\QuestsUpdated;
use App\Jobs\AssessQuestEnding;
use App\Models\FactAcknowledgement;
use App\Models\Inventory;
use App\Models\QuestEvent;
use App\Models\QuestOffer;
use App\Models\ResidentFeeling;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function runOf(WorldSession $session): WorldSessionQuest
{
    return $session->questRuns()->latest('id')->firstOrFail();
}

function syncQuests(WorldSession $session): void
{
    app(SyncSessionQuests::class)->handle($session->fresh());
}

it('starts a quest with a new session, and a beat about the spawn zone finishes at once', function () {
    Queue::fake([AssessQuestEnding::class]);
    [$user, , , $region] = worldStateScenario();
    $region->world->update(['spawn_region_id' => $region->id, 'spawn_passage_id' => 'studio-door']);
    worldQuest($region->world, ['beats' => [QuestFactory::beat('arrive', ['when' => ['enterZone' => ['region' => $region->id, 'zone' => 'studio']]])]]);

    $sessionId = $this->actingAs($user)->postJson(route('worlds.sessions.store', $region->world_id))->assertCreated()->json('id');

    $run = runOf(WorldSession::find($sessionId));
    expect($run->status)->toBe(QuestStatus::Completed)
        ->and($run->finishedBeats())->toBe(['arrive'])
        ->and($run->events()->pluck('type')->all())->toEqual([QuestEventType::Started, QuestEventType::BeatFinished, QuestEventType::Completed]);
    Queue::assertPushed(AssessQuestEnding::class, fn (AssessQuestEnding $job) => $job->runId === $run->id);
});

it('finishes a zone beat when the player crosses into it, counting the zone around a room, and only once', function () {
    [$user, , , $region, , $session] = worldStateScenario();
    $session->update(['position' => ['x' => 20, 'y' => 0, 'z' => 20]]);
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('studio', ['when' => ['enterZone' => ['region' => $region->id, 'zone' => 'studio']]]),
        QuestFactory::beat('later', ['requires' => ['studio']]),
    ]]);
    syncQuests($session);

    $this->actingAs($user)->putJson(route('worlds.sessions.position.update', [$region->world_id, $session->id]), ['position' => ['x' => -2, 'y' => 0, 'z' => 8]])->assertOk();
    $this->putJson(route('worlds.sessions.position.update', [$region->world_id, $session->id]), ['position' => ['x' => -3, 'y' => 0, 'z' => 9]])->assertOk();

    expect(runOf($session)->finishedBeats())->toBe(['studio'])
        ->and(QuestEvent::where('type', QuestEventType::BeatFinished)->count())->toBe(1);
});

it('finishes a first beat about something the player already holds the moment the quest starts', function () {
    [, , , $region, , $session] = worldStateScenario();
    $ledger = worldItem($region);
    $player = Inventory::factory()->forPlayer()->create(['world_session_id' => $session->id]);
    $player->items()->create(['item_id' => $ledger->id, 'quantity' => 1]);
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('hold', ['when' => ['has' => ['item' => $ledger->id, 'atLeast' => 1]]]),
        QuestFactory::beat('later', ['requires' => ['hold']]),
    ]]);

    syncQuests($session);

    expect(runOf($session)->finishedBeats())->toBe(['hold']);
});

it('finishes a beat in the same pass once the beats it requires finish, and announces each', function () {
    Event::fake([QuestsUpdated::class]);
    [, , , $region, , $session] = worldStateScenario();
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('arrive', ['when' => ['enterRegion' => $region->id]]),
        QuestFactory::beat('rich', ['requires' => ['arrive'], 'when' => ['credits' => ['atLeast' => 0]]]),
        QuestFactory::beat('last', ['requires' => ['rich']]),
    ]]);
    syncQuests($session);

    PlayerEnteredRegion::dispatch($session->id, $region->id);

    expect(runOf($session)->finishedBeats())->toBe(['arrive', 'rich']);
    Event::assertDispatched(QuestsUpdated::class, fn (QuestsUpdated $event) => collect($event->notices)->where('type', 'beatFinished')->count() === 2);
});

it('keeps a credits beat finished after the balance drops again', function () {
    [, , , $region, , $session] = worldStateScenario();
    $player = Inventory::factory()->forPlayer()->create(['world_session_id' => $session->id, 'credits' => 10]);
    worldQuest($region->world, ['beats' => [QuestFactory::beat('save', ['when' => ['credits' => ['atLeast' => 50]]]), QuestFactory::beat('later', ['requires' => ['save']])]]);
    syncQuests($session);

    app(TransferInventory::class)->handle(null, $player, 40, [], 'found');
    app(TransferInventory::class)->handle($player, null, 45, [], 'spent');

    expect(runOf($session)->finishedBeats())->toBe(['save']);
});

it('finishes a beat when a resident has learned a fact from the player', function () {
    [, , , $region, $resident, $session] = worldStateScenario();
    $fact = worldFact($resident);
    worldQuest($region->world, ['beats' => [QuestFactory::beat('tell', ['when' => ['acknowledged' => ['fact' => $fact->id, 'resident' => $resident->id]]]), QuestFactory::beat('later', ['requires' => ['tell']])]]);
    syncQuests($session);

    FactAcknowledgement::create(['world_session_id' => $session->id, 'fact_id' => $fact->id, 'world_resident_id' => $resident->id]);
    FactAcknowledged::dispatch($session->id);

    expect(runOf($session)->finishedBeats())->toBe(['tell']);
});

it('finishes a resident activity beat when a decision records it', function () {
    [, , , $region, $resident, $session] = worldStateScenario();
    worldQuest($region->world, ['beats' => [QuestFactory::beat('rest', ['when' => ['residentDid' => ['resident' => $resident->id, 'region' => $region->id, 'object' => 'pool-lounger-1', 'activity' => 'recline']]]), QuestFactory::beat('later', ['requires' => ['rest']])]]);
    syncQuests($session);

    app(RecordResidentActivity::class)->handle($session, $resident, $region, ['source' => 'idle', 'verb' => 'use', 'target' => 'pool-lounger-1-seat', 'activity' => 'recline', 'reason' => null, 'narration' => null, 'zone_id' => null]);

    expect(runOf($session)->finishedBeats())->toBe(['rest']);
});

it('completes a quest once every beat is finished, and fails it when failure and completion hold together', function () {
    [, , , $region, , $session] = worldStateScenario();
    worldQuest($region->world, ['beats' => [QuestFactory::beat('arrive', ['when' => ['enterRegion' => $region->id]])]], ['key' => 'plain']);
    worldQuest($region->world, [
        'beats' => [QuestFactory::beat('arrive', ['when' => ['enterRegion' => $region->id]])],
        'fail' => ['enterRegion' => $region->id],
    ], ['key' => 'doomed']);
    syncQuests($session);

    PlayerEnteredRegion::dispatch($session->id, $region->id);

    $runs = $session->questRuns()->with('quest')->get()->keyBy(fn ($run) => $run->quest->key);
    expect($runs['plain']->status)->toBe(QuestStatus::Completed)
        ->and($runs['doomed']->status)->toBe(QuestStatus::Failed)
        ->and($runs['doomed']->events()->where('type', QuestEventType::Failed)->first()->payload['completeAlsoHeld'])->toBeTrue();
});

it('never changes or removes a log entry', function () {
    $event = QuestEvent::factory()->create();

    expect(fn () => $event->update(['beat' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('brings a quest added later to a session when it is resumed', function () {
    [$user, , , $region, , $session] = worldStateScenario();
    worldQuest($region->world);

    $this->actingAs($user)->postJson(route('worlds.sessions.resume', [$region->world_id, $session->id]))->assertOk();

    expect(runOf($session)->status)->toBe(QuestStatus::Active);
});

it('drops beats removed from the definition from sessions playing it', function () {
    [$user, , , $region, , $session] = worldStateScenario();
    $quest = worldQuest($region->world, ['beats' => [QuestFactory::beat('gone'), QuestFactory::beat('kept'), QuestFactory::beat('open', ['requires' => ['kept']])]]);
    WorldSessionQuest::factory()->active()->create(['world_session_id' => $session->id, 'quest_id' => $quest->id, 'state' => [...WorldSessionQuest::EMPTY_STATE, 'finishedBeats' => ['gone', 'kept']]]);

    $this->actingAs($user)->patchJson(route('worlds.quests.update', [$region->world_id, $quest->id]), [
        'key' => $quest->key, 'title' => $quest->title, 'campaignId' => null,
        'definition' => [...$quest->definition, 'beats' => [QuestFactory::beat('kept'), QuestFactory::beat('open', ['requires' => ['kept']])]],
    ])->assertOk();

    expect(runOf($session)->finishedBeats())->toBe(['kept'])
        ->and(QuestEvent::where('type', QuestEventType::DefinitionEdited)->first()->payload['removedBeats'])->toBe(['gone']);
});

it('keeps a hidden beat from the player until it is finished', function () {
    [$user, , , $region, , $session] = worldStateScenario();
    worldQuest($region->world, ['beats' => [QuestFactory::beat('secret', ['hidden' => true, 'when' => ['enterRegion' => $region->id]]), QuestFactory::beat('open', ['requires' => ['secret']])]]);
    syncQuests($session);

    $beats = fn () => collect($this->actingAs($user)->getJson(route('worlds.sessions.quests.index', [$region->world_id, $session->id]))->json('runs.0.beats'))->pluck('id')->all();
    expect($beats())->toBe(['open']);

    PlayerEnteredRegion::dispatch($session->id, $region->id);

    expect($beats())->toBe(['secret', 'open']);
});

it('leaves other sessions untouched', function () {
    [, , , $region, , $session] = worldStateScenario();
    $other = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $region->id]);
    worldQuest($region->world, ['beats' => [QuestFactory::beat('arrive', ['when' => ['enterRegion' => $region->id]]), QuestFactory::beat('later', ['requires' => ['arrive']])]]);
    syncQuests($session);
    syncQuests($other);

    PlayerEnteredRegion::dispatch($session->id, $region->id);

    expect(runOf($session)->finishedBeats())->toBe(['arrive'])->and(runOf($other)->finishedBeats())->toBe([]);
});

it('finishes a beat the moment a resident\'s trust reaches what it asks, with the change as its cause', function () {
    [, , , $region, $resident, $session] = worldStateScenario();
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('trusted', ['when' => ['feeling' => ['resident' => $resident->id, 'kind' => 'trust', 'atLeast' => 3]]]),
        QuestFactory::beat('later', ['requires' => ['trusted']]),
    ]]);
    syncQuests($session);
    $feeling = ResidentFeeling::of($session, $resident);

    $feeling->adjust(['trust' => 2]);
    expect(runOf($session)->finishedBeats())->toBe([]);

    $feeling->adjust(['trust' => 1]);
    $finished = QuestEvent::where('type', QuestEventType::BeatFinished)->firstOrFail();
    expect(runOf($session)->finishedBeats())->toBe(['trusted'])
        ->and($finished->payload['because'])->toBe("{$resident->assistant->name}'s trust is now 3.0");
});

it('reads a feeling bound from above, and a resident with no feelings yet as 0', function () {
    [, , , $region, $resident, $session] = worldStateScenario();
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('cold', ['when' => ['feeling' => ['resident' => $resident->id, 'kind' => 'romance', 'atMost' => -2]]]),
        QuestFactory::beat('neutral', ['when' => ['all' => [['feeling' => ['resident' => $resident->id, 'kind' => 'liking', 'atLeast' => 0, 'atMost' => 0]], ['flag' => 'go']]]]),
        QuestFactory::beat('later', ['requires' => ['cold', 'neutral']]),
    ]]);
    syncQuests($session);
    $run = runOf($session);
    $run->mergeState(['flags' => ['go' => ['by' => null, 'reason' => null]]]);
    $run->save();

    ResidentFeeling::of($session, $resident)->adjust(['romance' => -2]);

    expect(runOf($session)->finishedBeats())->toEqualCanonicalizing(['cold', 'neutral']);
});

it('counts what the player handed a resident across handovers, and never what came back', function () {
    [, , , $region, $resident, $session, $player, $residentInventory] = inventoryScenario();
    $bread = worldItem($region, ['name' => 'bread']);
    $player->items()->create(['item_id' => $bread->id, 'quantity' => 5]);
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('fed', ['when' => ['gaveTo' => ['resident' => $resident->id, 'item' => $bread->id, 'atLeast' => 3]]]),
        QuestFactory::beat('paid', ['when' => ['spentWith' => ['resident' => $resident->id, 'atLeast' => 50]]]),
        QuestFactory::beat('later', ['requires' => ['fed', 'paid']]),
    ]]);
    syncQuests($session);
    $transfer = app(TransferInventory::class);

    $transfer->handle($player, $residentInventory, 20, [$bread->id => 1], 'gift');
    $transfer->handle($residentInventory, $player, 0, [$bread->id => 1], 'gift');
    $transfer->handle($player, $residentInventory, 0, [$bread->id => 1], 'gift');
    expect(runOf($session)->finishedBeats())->toBe([]);

    $transfer->handle($player, $residentInventory, 30, [$bread->id => 1], 'trade');

    $fed = QuestEvent::where('type', QuestEventType::BeatFinished)->where('beat', 'fed')->firstOrFail();
    expect(runOf($session)->finishedBeats())->toEqualCanonicalizing(['fed', 'paid'])
        ->and($fed->payload['because'])->toBe("The user gave {$resident->assistant->name} 1 bread and paid them 30 credits");
});

it('reads another quest\'s state and how often its offer was turned down, walking away included', function () {
    [, , , $region, $resident, $session] = worldStateScenario();
    $ledger = worldQuest($region->world, ['start' => ['mode' => 'offer', 'giver' => $resident->id]], ['key' => 'the-ledger', 'title' => 'The Ledger']);
    worldQuest($region->world, ['beats' => [
        QuestFactory::beat('declined', ['when' => ['questState' => ['quest' => 'the-ledger', 'state' => 'declined']]]),
        QuestFactory::beat('twice', ['when' => ['declinedTimes' => ['quest' => 'the-ledger', 'atLeast' => 2]]]),
        QuestFactory::beat('later', ['requires' => ['declined', 'twice']]),
    ]], ['key' => 'watcher']);
    syncQuests($session);
    $ledgerRun = $session->questRuns()->where('quest_id', $ledger->id)->firstOrFail();
    $watcher = fn () => $session->questRuns()->whereHas('quest', fn ($query) => $query->where('key', 'watcher'))->firstOrFail();

    QuestOffer::factory()->create(['world_session_id' => $session->id, 'world_session_quest_id' => $ledgerRun->id, 'world_resident_id' => $resident->id, 'status' => QuestOfferStatus::Declined]);
    QuestStateChanged::dispatch($session->id, 'The user declined "The Ledger"');
    expect($watcher()->finishedBeats())->toBe(['declined']);

    $pending = QuestOffer::factory()->create(['world_session_id' => $session->id, 'world_session_quest_id' => $ledgerRun->id, 'world_resident_id' => $resident->id, 'status' => QuestOfferStatus::Pending]);
    app(WithdrawQuestOffers::class)->handle(QuestOffer::whereKey($pending->id));

    expect($watcher()->finishedBeats())->toEqualCanonicalizing(['declined', 'twice'])
        ->and(QuestSessionState::for($session->fresh())->questState('the-ledger'))->toBe('declined');
});

it('reads a quest as active once its offer is accepted, and starts a quest on a feeling', function () {
    [, , , $region, $resident, $session] = worldStateScenario();
    worldQuest($region->world, ['start' => ['mode' => 'condition', 'when' => ['feeling' => ['resident' => $resident->id, 'kind' => 'liking', 'atLeast' => 4]]]], ['key' => 'fond']);
    syncQuests($session);
    $run = runOf($session);

    ResidentFeeling::of($session, $resident)->adjust(['liking' => 4]);

    expect($run->fresh()->status)->toBe(QuestStatus::Active)
        ->and(QuestSessionState::for($session->fresh())->questState('fond'))->toBe('active');
});

it('counts no handovers, payments or declines of another session', function () {
    [, , , $region, $resident, $session, $player, $residentInventory] = inventoryScenario();
    $other = WorldSession::factory()->create(['world_user_id' => $session->world_user_id, 'region_id' => $region->id]);
    $otherPlayer = Inventory::factory()->forPlayer()->create(['world_session_id' => $other->id, 'credits' => 100]);
    $otherResident = Inventory::factory()->forResident($resident)->create(['world_session_id' => $other->id]);

    app(TransferInventory::class)->handle($otherPlayer, $otherResident, 40, [], 'gift');

    expect(QuestSessionState::for($session)->spentWith($resident->id))->toBe(0)
        ->and(QuestSessionState::for($other)->spentWith($resident->id))->toBe(40);
});
