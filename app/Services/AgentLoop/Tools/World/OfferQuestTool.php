<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\RecordQuestEvent;
use App\Contracts\AgentTool;
use App\Enums\QuestEventType;
use App\Enums\QuestOfferStatus;
use App\Enums\QuestStatus;
use App\Models\Conversation;
use App\Models\QuestOffer;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The giver of a quest offers it to the user, who accepts or declines on a card.
 */
class OfferQuestTool implements AgentTool
{
    public ?QuestOffer $offer = null;

    public function __construct(
        private readonly WorldSession $session,
        private readonly Conversation $conversation,
        private readonly WorldResident $giver,
    ) {}

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
        app(RecordQuestEvent::class)->handle($run, QuestEventType::Offered, payload: ['residentId' => $this->giver->id, 'residentName' => $this->giver->assistant->name]);

        return ['status' => 'offered', 'note' => 'You offered it; they will answer.'];
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
