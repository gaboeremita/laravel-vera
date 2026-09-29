<?php

use App\Actions\Quests\FindQuestReferences;
use App\Actions\Quests\ValidateQuestDefinition;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestsUpdated;
use App\Exceptions\UsedByQuests;
use App\Jobs\AssessQuestEnding;
use App\Jobs\GiveQuestReward;
use App\Models\AiModel;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\QuestEvent;
use App\Models\ResidentFeeling;
use App\Models\WorldSessionQuest;
use Database\Factories\QuestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const REWARD_PROSE = 'Give a reward based on the score: a spray can for a low score, the plating for a high one. Thank them accordingly.';

/**
 * A completed, scored run of a quest whose reward comes from the resident,
 * who holds two spray cans and one plating, or from the pool lounger, with
 * the player's conversation with the resident in the session.
 */
function rewardScenario(bool $fromObject = false, QuestStatus $status = QuestStatus::Completed): array
{
    $scenario = inventoryScenario(playerCredits: 10);
    [, , $conversation, $region, $resident, $session, , $residentInventory] = $scenario;
    $conversation->update(['world_session_id' => $session->id]);
    $sprayCan = worldItem($region, ['name' => 'Spray can']);
    $plating = worldItem($region, ['name' => 'Compliance Unit plating']);
    InventoryItem::factory()->create(['inventory_id' => $residentInventory->id, 'item_id' => $sprayCan->id, 'quantity' => 2]);
    InventoryItem::factory()->create(['inventory_id' => $residentInventory->id, 'item_id' => $plating->id, 'quantity' => 1]);
    $from = $fromObject ? ['object' => ['region' => $region->id, 'object' => 'pool-lounger-1']] : ['resident' => $resident->id];
    $quest = worldQuest($region->world, ['reward' => ['from' => $from, 'prose' => REWARD_PROSE]], ['title' => 'Broth for the Diver']);
    $run = WorldSessionQuest::factory()->withEnding(['tier' => 'warm', 'scores' => [['dimension' => 'care', 'score' => 8, 'reason' => 'They stayed.']]])
        ->create(['world_session_id' => $session->id, 'quest_id' => $quest->id, 'status' => $status]);

    return [...$scenario, $run, $sprayCan, $plating];
}

function rewardCall(array $items, int $credits = 0, string $line = 'Thanks for the broth. Take this.'): array
{
    return toolCallResponse('reward_1', 'give_reward', ['credits' => $credits, 'items' => $items, 'line' => $line]);
}

function heldQuantity($inventory, $item): ?int
{
    return $inventory->items()->where('item_id', $item->id)->value('quantity');
}

it('refuses a reward without prose or with a giver that is not in the world', function (array $reward, string $path) {
    [, , , $region] = worldStateScenario(fakeReply: false);
    $definition = [...QuestFactory::defaultDefinition(), 'reward' => $reward];

    $errors = app(ValidateQuestDefinition::class)->handle($region->world, 'with-reward', $definition)['errors'];

    expect($errors)->toHaveKey($path);
})->with([
    'no prose' => [['from' => ['resident' => 1], 'prose' => ' '], 'reward.prose'],
    'unknown resident' => [['from' => ['resident' => 999999], 'prose' => 'Reward them.'], 'reward.from.resident'],
    'unknown object' => [['from' => ['object' => ['region' => 0, 'object' => 'chest']], 'prose' => 'Reward them.'], 'reward.from.object.region'],
    'no giver' => [['from' => [], 'prose' => 'Reward them.'], 'reward.from'],
]);

it('accepts a reward from a resident or from an object of the world', function () {
    [, , , $region, $resident] = worldStateScenario(fakeReply: false);
    $validate = fn (array $from) => app(ValidateQuestDefinition::class)->handle($region->world, 'with-reward', [...QuestFactory::defaultDefinition(), 'reward' => ['from' => $from, 'prose' => 'Reward them.']])['errors'];

    expect($validate(['resident' => $resident->id]))->toBe([])
        ->and($validate(['object' => ['region' => $region->id, 'object' => 'pool-lounger-1']]))->toBe([])
        ->and($validate(['object' => ['region' => $region->id, 'object' => 'no-such-thing']]))->toHaveKey('reward.from.object.object');
});

it('keeps the resident who gives a reward from being deleted', function () {
    [, , , $region, $resident] = worldStateScenario(fakeReply: false);
    worldQuest($region->world, ['reward' => ['from' => ['resident' => $resident->id], 'prose' => 'Reward them.']], ['title' => 'Paid in Kind']);

    expect(fn () => app(FindQuestReferences::class)->ensureResidentUnused($resident))->toThrow(UsedByQuests::class);
});

it('queues the reward once the ending of a completed quest is written', function () {
    [, , , , , , , , $run] = rewardScenario();
    $run->quest->world->update(['narrator_model_id' => AiModel::first()->id]);
    Queue::fake([GiveQuestReward::class]);
    fakeTurn(toolCallResponse('ending_1', 'record_ending', ['title' => 'Fed', 'epilogue' => 'Oxygen ate.', 'scores' => [['dimension' => 'care', 'score' => 8, 'reason' => 'They stayed.']], 'resultingFlags' => []]));

    AssessQuestEnding::dispatchSync($run->id);

    Queue::assertPushed(GiveQuestReward::class, fn (GiveQuestReward $job) => $job->runId === $run->id);
});

it('lets the resident decide the reward in their own voice from the score and what they hold, and hands it over', function () {
    [, , $conversation, , $resident, $session, $player, $residentInventory, $run, , $plating] = rewardScenario();
    Event::fake([QuestsUpdated::class]);
    fakeTurn(toolCallResponse('reward_1', 'give_reward', ['credits' => 15, 'items' => [['item' => 'Compliance Unit plating', 'quantity' => 1]], 'line' => 'Thanks for the broth. Take this.', 'feelings' => ['liking' => -1, 'trust' => 5]]));

    GiveQuestReward::dispatchSync($run->id);

    $request = Http::recorded()[0][0];
    $sent = collect($request['messages'])->pluck('content')->implode("\n");
    expect($sent)->toContain(REWARD_PROSE)->toContain('Total score: 8/10')->toContain('How you feel about the user (-10 to 10): romance 0, trust 0, liking 0')->toContain('care 8/10 (They stayed.)')->toContain('2 Spray can')
        ->and($request['tools'][0]['function']['parameters']['properties']['items']['items']['properties']['item']['enum'])->toBe(['Spray can', 'Compliance Unit plating'])
        ->and(heldQuantity($player, $plating))->toBe(1)
        ->and(heldQuantity($residentInventory, $plating))->toBeNull()
        ->and($player->fresh()->credits)->toBe(25)
        ->and(ResidentFeeling::of($session, $resident)->values())->toBe(['romance' => 0.0, 'trust' => 3.0, 'liking' => -1.0])
        ->and($run->fresh()->state['reward'])->toMatchArray(['line' => 'Thanks for the broth. Take this.', 'credits' => 15, 'items' => [['name' => 'Compliance Unit plating', 'quantity' => 1]]])
        ->and(QuestEvent::where('type', QuestEventType::RewardGiven)->first()->payload)->toMatchArray(['prose' => REWARD_PROSE, 'credits' => 15])
        ->and($conversation->messages()->latest('id')->first())->role->toBe('assistant')->content->toBe('Thanks for the broth. Take this.');

    Event::assertDispatched(QuestsUpdated::class, fn (QuestsUpdated $event) => $event->sessionId === $session->id && $event->notices[0]['type'] === 'rewardGiven' && $event->notices[0]['inventory'] !== null);
});

it('hands over only what the giver holds', function () {
    [, , , , , , $player, , $run, $sprayCan] = rewardScenario();
    fakeTurn(rewardCall([['item' => 'Spray can', 'quantity' => 5], ['item' => 'Golden crown', 'quantity' => 1]]));

    GiveQuestReward::dispatchSync($run->id);

    expect(heldQuantity($player, $sprayCan))->toBe(2)
        ->and($run->fresh()->state['reward']['items'])->toBe([['name' => 'Spray can', 'quantity' => 2]]);
});

it('lets the narrator give the reward from an object, within the credits it holds', function () {
    [, , , $region, , $session, $player, , $run] = rewardScenario(fromObject: true);
    $region->world->update(['narrator_model_id' => AiModel::first()->id]);
    $lounger = Inventory::factory()->forObject($region->id, 'pool-lounger-1')->create(['world_session_id' => $session->id, 'credits' => 30]);
    fakeTurn(rewardCall([], credits: 100, line: 'Under the cushion you find a roll of credits.'));

    GiveQuestReward::dispatchSync($run->id);

    expect(collect(Http::recorded()[0][0]['messages'])->firstWhere('role', 'system')['content'])->toContain('its reward comes from Pool lounger')
        ->and($lounger->fresh()->credits)->toBe(0)
        ->and($player->fresh()->credits)->toBe(40)
        ->and($run->fresh()->state['reward'])->toMatchArray(['giverName' => 'Pool lounger', 'line' => 'Under the cushion you find a roll of credits.']);
});

it('gives nothing for a quest that did not complete, and pays a reward only once', function () {
    [, , , , , , , , $failedRun] = rewardScenario(status: QuestStatus::Failed);
    fakeTurn(rewardCall([['item' => 'Spray can', 'quantity' => 1]]));

    GiveQuestReward::dispatchSync($failedRun->id);
    expect(Http::recorded())->toHaveCount(0);

    $failedRun->update(['status' => QuestStatus::Completed]);
    GiveQuestReward::dispatchSync($failedRun->id);
    GiveQuestReward::dispatchSync($failedRun->id);

    expect(Http::recorded())->toHaveCount(1)
        ->and(QuestEvent::where('type', QuestEventType::RewardGiven)->count())->toBe(1);
});
