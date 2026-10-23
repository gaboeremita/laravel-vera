<?php

use App\Actions\Quests\SyncSessionQuests;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Jobs\AssessQuestEnding;
use App\Models\QuestEvent;
use App\Models\Region;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function creatorQuestMenuScenario(array $definition = []): array
{
    $scenario = worldStateScenario(fakeReply: false);
    [, , , $region, , $session] = $scenario;
    $session->update(['creator_mode_at' => now()]);
    $quest = worldQuest($region->world, ['beats' => [QuestFactory::beat('a'), QuestFactory::beat('b', ['requires' => ['a']]), QuestFactory::beat('c', ['requires' => ['b']])], ...$definition], ['title' => 'The Mill']);
    app(SyncSessionQuests::class)->handle($session->fresh());

    return [...$scenario, $quest];
}

it('starts a quest whose start condition doesn\'t hold', function () {
    [$user, , , $region, , $session, $quest] = creatorQuestMenuScenario(['start' => ['mode' => 'condition', 'when' => ['credits' => ['atLeast' => 9999]]]]);
    expect($session->latestRunOf($quest)?->status)->not->toBe(QuestStatus::Active);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.quests.start', [$region->world_id, $session->id, $quest->id]))->assertNoContent();

    expect($session->latestRunOf($quest)->status)->toBe(QuestStatus::Active)
        ->and(QuestEvent::where('type', QuestEventType::Started)->where('by_creator', true)->exists())->toBeTrue();
});

it('refuses to start a quest that is already going on', function () {
    [$user, , , $region, , $session, $quest] = creatorQuestMenuScenario();

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.quests.start', [$region->world_id, $session->id, $quest->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'That quest is already going on.');
});

it('fails a quest so its ending is written, then reopens it keeping its log', function () {
    Queue::fake([AssessQuestEnding::class]);
    [$user, , , $region, , $session, $quest] = creatorQuestMenuScenario();

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.quests.end', [$region->world_id, $session->id, $quest->id]), ['outcome' => 'failed'])->assertNoContent();
    expect($session->latestRunOf($quest)->status)->toBe(QuestStatus::Failed);
    Queue::assertPushed(AssessQuestEnding::class);
    $eventsBefore = QuestEvent::count();

    $this->postJson(route('worlds.sessions.creator.quests.reset', [$region->world_id, $session->id, $quest->id]))->assertNoContent();

    expect($session->latestRunOf($quest)->status)->toBe(QuestStatus::Active)
        ->and(QuestEvent::count())->toBeGreaterThan($eventsBefore)
        ->and(QuestEvent::where('type', QuestEventType::Reset)->where('by_creator', true)->exists())->toBeTrue();

    $this->postJson(route('worlds.sessions.creator.quests.end', [$region->world_id, $session->id, $quest->id]), ['outcome' => 'failed'])->assertNoContent();
    $this->postJson(route('worlds.sessions.creator.quests.end', [$region->world_id, $session->id, $quest->id]), ['outcome' => 'completed'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'That quest isn\'t going on.');
});

it('finishes beats and undoes one with the beats requiring it', function () {
    [$user, , , $region, , $session, $quest] = creatorQuestMenuScenario();
    $beat = fn (string $id, bool $finished) => $this->actingAs($user)->putJson(route('worlds.sessions.creator.quests.beats.update', [$region->world_id, $session->id, $quest->id, $id]), ['finished' => $finished]);

    $beat('a', true)->assertSuccessful();
    $beat('b', true)->assertSuccessful();
    $beat('a', false)->assertSuccessful()->assertJsonPath('undone', ['a', 'b']);

    expect($session->latestRunOf($quest)->finishedBeats())->toBe([])
        ->and(QuestEvent::whereIn('type', [QuestEventType::BeatFinished, QuestEventType::BeatUndone])->where('by_creator', false)->exists())->toBeFalse();
});

it('sets and clears a flag', function () {
    [$user, , , $region, , $session, $quest] = creatorQuestMenuScenario();
    $route = route('worlds.sessions.creator.quests.flags.update', [$region->world_id, $session->id, $quest->id, 'metRin']);

    $this->actingAs($user)->putJson($route, ['on' => true])->assertNoContent();
    expect($session->latestRunOf($quest)->hasFlag('metRin'))->toBeTrue();

    $this->putJson($route, ['on' => false])->assertNoContent();
    expect($session->latestRunOf($quest)->hasFlag('metRin'))->toBeFalse()
        ->and(QuestEvent::whereIn('type', [QuestEventType::FlagSet, QuestEventType::FlagCleared])->where('by_creator', true)->count())->toBe(2);
});

it('refuses a quest of another world', function () {
    [$user, , , $region, , $session] = creatorQuestMenuScenario();
    $otherRegion = Region::factory()->forUser($user)->create();
    $otherQuest = worldQuest($otherRegion->world);

    $this->actingAs($user)->postJson(route('worlds.sessions.creator.quests.start', [$region->world_id, $session->id, $otherQuest->id]))->assertNotFound();
});
