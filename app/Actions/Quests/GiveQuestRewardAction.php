<?php

namespace App\Actions\Quests;

use App\Actions\AppendWorldConversationContext;
use App\Actions\BuildFeelingsPrompt;
use App\Actions\Narrate;
use App\Actions\ResolveInventory;
use App\Actions\ResolveNarratorModel;
use App\Actions\ResolveResidentRegion;
use App\Actions\TransferInventory;
use App\Contracts\LlmProvider;
use App\Directors\PromptDirector;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Models\AssistantUser;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Region;
use App\Models\ResidentFeeling;
use App\Models\WorldResident;
use App\Models\WorldSessionQuest;
use App\Services\AgentLoop\Tools\World\GiveRewardTool;
use App\Services\LlmProviders\LlmManager;
use RuntimeException;

/**
 * Pays a completed quest's reward. The giver named in the definition reads
 * what the author told them, how the quest went and what they hold, and
 * decides what to hand over: a resident on their own model and in their own
 * voice, an object through the world's narrator.
 */
class GiveQuestRewardAction
{
    private const EXCLUDED_PROMPT_SECTIONS = ['world_awareness', 'world_places', 'opening_message', 'voice mode', 'image handling', 'OOC mode', 'emotion tags', 'pose tags', 'secret trigger', 'creator mode'];

    public function __construct(
        private readonly ResolveInventory $resolveInventory,
        private readonly TransferInventory $transferInventory,
        private readonly RecordQuestEvent $recordQuestEvent,
        private readonly BroadcastQuestRuns $broadcastQuestRuns,
    ) {}

    public function handle(WorldSessionQuest $run): void
    {
        $run->loadMissing(['quest', 'worldSession.worldUser.world']);
        $reward = $run->quest->reward();
        if ($reward === null || $run->status !== QuestStatus::Completed || isset($run->state['reward'])) {
            return;
        }

        $session = $run->worldSession;
        $world = $session->worldUser->world;
        $resident = isset($reward['from']['resident']) ? $world->residents()->with('assistant')->findOrFail($reward['from']['resident']) : null;
        $region = $resident === null ? $world->regions()->findOrFail($reward['from']['object']['region']) : null;
        $giver = $resident !== null
            ? $this->resolveInventory->forResident($session, $resident)
            : $this->resolveInventory->forObject($session, $region, $reward['from']['object']['object']);
        $giverName = $resident?->assistant->name ?? ($region->layoutObject($reward['from']['object']['object'])['name'] ?? $reward['from']['object']['object']);

        $feeling = $resident !== null ? ResidentFeeling::of($session, $resident) : null;
        $tool = new GiveRewardTool($this->held($giver), $giver->holder->countsCredits() ? $giver->credits : null, withFeelings: $feeling !== null);
        [$provider, $system] = $resident !== null
            ? $this->asResident($run, $resident)
            : $this->asObject($run, $region, $giverName);

        $response = $provider->chat(
            messages: [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $this->situation($run, $reward['prose'], $giver, $feeling)]],
            tools: [['name' => $tool->name(), 'description' => $tool->description(), 'parameters' => $tool->parameters()]],
        );
        $call = collect($response->toolCalls)->firstWhere('name', $tool->name())
            ?? throw new RuntimeException("{$giverName} gave no reward for {$run->quest->title}.");
        $given = $tool->handle($call->arguments);

        $player = $this->resolveInventory->forPlayer($session);
        $this->transferInventory->handle($giver, $player, $given['credits'], $given['items'], "Reward for {$run->quest->title}");

        if ($feeling !== null && $given['feelings'] !== []) {
            $feeling->adjust($given['feelings']);
        }

        $received = collect($given['items'])->map(fn (int $quantity, int $itemId) => ['name' => Item::find($itemId)->name, 'quantity' => $quantity])->values()->all();
        $record = ['giverName' => $giverName, 'line' => $given['line'], 'credits' => $given['credits'], 'items' => $received];
        $run->mergeState(['reward' => $record]);
        $run->save();
        $this->recordQuestEvent->handle($run, QuestEventType::RewardGiven, payload: [...$record, 'prose' => $reward['prose'], 'feelings' => $given['feelings']]);

        if ($resident !== null && $given['line'] !== '') {
            $this->tellInConversation($run, $resident, $given['line']);
        }

        $this->broadcastQuestRuns->handle($session->id, [$run], [[
            ...BroadcastQuestRuns::notice('rewardGiven', $run, $given['line']),
            'giverName' => $giverName,
            'fromResident' => $resident !== null,
            'reward' => $record,
            'inventory' => $player->summary(),
        ]]);
    }

    /**
     * @return array{0: LlmProvider, 1: string}
     */
    private function asResident(WorldSessionQuest $run, WorldResident $resident): array
    {
        $session = $run->worldSession;
        $assistantUser = AssistantUser::where('assistant_id', $resident->assistant_id)->where('user_id', $session->worldUser->user_id)->firstOrFail();
        $region = app(ResolveResidentRegion::class)->handle($session, $resident);
        $director = (new PromptDirector(app(AppendWorldConversationContext::class)->handle($resident->assistant, $region)))->except(self::EXCLUDED_PROMPT_SECTIONS);
        $director->append('the reward', 'The user just completed a quest you are part of, and you are the one who rewards them. Read what the story asks of you, how the user did, how you feel about them and what you hold, then decide in character what to give and call the give_reward tool once, with what you say to the user as you hand it over. Let the quest move your feelings about the user too, usually 1 to 3 points each: someone rude who still got the job done might earn your trust and lose some of your liking.');

        return [(new LlmManager)->forAssistantUser($assistantUser), $director->build()];
    }

    /**
     * @return array{0: LlmProvider, 1: string}
     */
    private function asObject(WorldSessionQuest $run, Region $region, string $objectName): array
    {
        $world = $run->worldSession->worldUser->world;
        $system = implode("\n\n", [
            "You are the narrator of {$world->name}, a role-playing world. {$world->description}",
            "The scene is {$region->name}: {$region->description}",
            "The user just completed a quest, and its reward comes from {$objectName}. Read what the story asks for, how the user did and what {$objectName} holds, then decide what the user receives and call the give_reward tool once, describing in one to three sentences in second person what they find.",
        ]);

        return [app(ResolveNarratorModel::class)->handle($world), $system];
    }

    private function situation(WorldSessionQuest $run, string $prose, Inventory $giver, ?ResidentFeeling $feeling): string
    {
        $ending = $run->ending ?? [];
        $scores = collect($ending['scores'] ?? []);
        $total = $scores->isEmpty() ? null : round($scores->avg('score'), 1);

        return collect([
            'Quest' => "{$run->quest->title}: {$run->quest->description()}",
            'Ending' => trim(($ending['title'] ?? '').(($ending['tier'] ?? null) ? " ({$ending['tier']})" : '').': '.($ending['epilogue'] ?? ''), ' :'),
            'Total score' => $total !== null ? "{$total}/10" : null,
            'Scores' => $scores->isEmpty() ? null : $scores->map(fn (array $score) => "{$score['dimension']} {$score['score']}/10 ({$score['reason']})")->implode('; '),
            'How you feel about the user (-10 to 10)' => $feeling === null ? null : collect($feeling->values())->map(fn (float $value, string $name) => "{$name} ".BuildFeelingsPrompt::format($value))->implode(', '),
            'Holding' => app(Narrate::class)->holdings($giver),
            'What the story asks of the reward' => $prose,
        ])->filter()->map(fn (string $value, string $label) => "{$label}: {$value}")->implode("\n");
    }

    /**
     * @return array<string, array{id: int, quantity: ?int}>
     */
    private function held(Inventory $inventory): array
    {
        return $inventory->items()->with('item')->get()
            ->mapWithKeys(fn (InventoryItem $held) => [$held->item->name => ['id' => $held->item_id, 'quantity' => $held->quantity]])
            ->all();
    }

    /**
     * The resident's line lands in their conversation with the user in this
     * session, so it shows when the chat is opened and they remember saying it.
     */
    private function tellInConversation(WorldSessionQuest $run, WorldResident $resident, string $line): void
    {
        $session = $run->worldSession;
        $assistantUser = AssistantUser::where('assistant_id', $resident->assistant_id)->where('user_id', $session->worldUser->user_id)->firstOrFail();
        $conversation = $assistantUser->conversations()->where('world_session_id', $session->id)->first()
            ?? $assistantUser->conversations()->create(['title' => 'New conversation', 'world_session_id' => $session->id]);
        $conversation->messages()->create(['role' => 'assistant', 'content' => $line]);
    }
}
