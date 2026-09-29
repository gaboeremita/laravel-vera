<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\LookUpOfferCondition;
use App\Actions\Quests\OfferMoment;
use App\Actions\Quests\QuestSessionState;
use App\Actions\Quests\RecordQuestEvent;
use App\Contracts\AgentTool;
use App\Enums\QuestEventType;
use App\Enums\QuestOfferStatus;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestStateChanged;
use App\Models\Conversation;
use App\Models\QuestOffer;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The giver of a quest offers it to the user, who accepts or declines on a card.
 * What the quest asks before being offered never stops the offer: the giver
 * decides, and the offer records what they checked and whether it held.
 */
class OfferQuestTool implements AgentTool
{
    public ?QuestOffer $offer = null;

    private ?CheckOfferConditionTool $checks = null;

    public function __construct(
        private readonly WorldSession $session,
        private readonly Conversation $conversation,
        private readonly WorldResident $giver,
        private readonly ?OfferMoment $moment = null,
    ) {}

    /**
     * The giver's checks this turn, recorded with the offer.
     */
    public function recordChecksOf(CheckOfferConditionTool $checks): void
    {
        $this->checks = $checks;
    }

    public function name(): string
    {
        return 'offer_quest';
    }

    public function description(): string
    {
        return 'Offers the user a task you have for them, after you bring it up in your own words. They see the offer and accept or decline it; the answer reaches you as their next message.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'quest' => ['type' => 'string', 'enum' => $this->offerable()->keys()->values()->all(), 'description' => 'The task you offer.'],
            ],
            'required' => ['quest'],
        ];
    }

    public function handle(array $arguments): array
    {
        if ($this->offer !== null) {
            throw new RuntimeException('You already offered something this turn; wait for the answer.');
        }
        $run = $this->offerable()->get(trim((string) ($arguments['quest'] ?? '')))
            ?? throw new RuntimeException('That isn\'t a task you can offer right now.');

        $this->offer = QuestOffer::create([
            'world_session_id' => $this->session->id,
            'world_session_quest_id' => $run->id,
            'conversation_id' => $this->conversation->id,
            'world_resident_id' => $this->giver->id,
            'status' => QuestOfferStatus::Pending,
        ]);
        app(RecordQuestEvent::class)->handle($run, QuestEventType::Offered, payload: ['residentId' => $this->giver->id, 'residentName' => $this->giver->assistant->name, ...$this->checked($run)]);
        QuestStateChanged::dispatch($this->session->id, "{$this->giver->assistant->name} offered \"{$run->quest->title}\" to the user");

        return ['status' => 'offered', 'note' => 'You offered it; they will answer.'];
    }

    /**
     * @return array{lookups?: array<int, array<string, string>>, offerWhenHeld?: bool, unmetParts?: array<int, string>}
     */
    private function checked(WorldSessionQuest $run): array
    {
        if ($run->quest->offerWhen() === null || $this->moment === null) {
            return [];
        }

        $lookUp = app(LookUpOfferCondition::class);
        $state = QuestSessionState::for($this->session);
        $unmet = $lookUp->unmetParts($run, $state, $this->moment);

        return [
            'lookups' => $this->checks?->lookups ?? [],
            'offerWhenHeld' => $lookUp->holds($run, $state, $this->moment),
            'unmetParts' => $unmet,
        ];
    }

    /**
     * Available quests this resident gives that aren't already offered in
     * this conversation, by title.
     *
     * @return Collection<string, WorldSessionQuest>
     */
    public function offerable(): Collection
    {
        $pending = QuestOffer::where('conversation_id', $this->conversation->id)->where('status', QuestOfferStatus::Pending)->pluck('world_session_quest_id')->all();

        return $this->session->questRuns()->with('quest')->where('status', QuestStatus::Available)->get()
            ->filter(fn (WorldSessionQuest $run) => $run->quest->giverId() === $this->giver->id && ! in_array($run->id, $pending, true))
            ->keyBy(fn (WorldSessionQuest $run) => $run->quest->title);
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
