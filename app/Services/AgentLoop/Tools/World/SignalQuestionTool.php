<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\OfferQuestionStatus;
use App\Actions\Quests\RecordQuestEvent;
use App\Contracts\AgentTool;
use App\Enums\QuestEventType;
use App\Enums\QuestOfferStatus;
use App\Enums\QuestStatus;
use App\Jobs\JudgeQuestion;
use App\Models\Conversation;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * A resident named on a judged question says they believe the user has met
 * it; a separate model then checks the conversation.
 */
class SignalQuestionTool implements AgentTool
{
    public function __construct(
        private readonly WorldSession $session,
        private readonly Conversation $conversation,
        private readonly WorldResident $resident,
    ) {}

    public function name(): string
    {
        return 'signal_question';
    }

    public function description(): string
    {
        return 'Says you believe the user has done what a question you keep in mind asks. Give your reason; the story checks it.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'question' => ['type' => 'string', 'enum' => $this->signallable()->keys()->values()->all(), 'description' => 'The question you believe they have met.'],
                'reason' => ['type' => 'string', 'description' => 'Why you believe it, in a sentence.'],
            ],
            'required' => ['question', 'reason'],
        ];
    }

    public function handle(array $arguments): array
    {
        $text = trim((string) ($arguments['question'] ?? ''));
        $questions = $this->signallable()->get($text) ?? throw new RuntimeException('That isn\'t a question you keep in mind right now.');
        $reason = trim((string) ($arguments['reason'] ?? '')) ?: 'no reason given';

        foreach ($questions as ['run' => $run, 'beat' => $beatId, 'question' => $questionId]) {
            app(RecordQuestEvent::class)->handle($run, QuestEventType::QuestionSignalled, $beatId, ['question' => $questionId, 'reason' => $reason, 'residentId' => $this->resident->id, 'residentName' => $this->resident->assistant->name, 'conversationId' => $this->conversation->id, ...OfferQuestionStatus::textHash($run, $questionId)]);
            JudgeQuestion::dispatch($run->id, $questionId, $this->conversation->id, $this->resident->id);
        }

        return ['status' => 'signalled', 'note' => 'Noted; carry on in character.'];
    }

    /**
     * The unmet questions of current beats that name this resident, and the
     * unmet offerQuestions of the quests they could offer, by text.
     *
     * @return Collection<string, array<int, array{run: WorldSessionQuest, beat: ?string, question: string}>>
     */
    public function signallable(): Collection
    {
        $runs = $this->session->questRuns()->with('quest')->whereIn('status', [QuestStatus::Active, QuestStatus::Available])->get();
        $beatQuestions = $runs->where('status', QuestStatus::Active)
            ->flatMap(fn (WorldSessionQuest $run) => collect($run->currentBeats())
                ->flatMap(fn (array $beat) => collect($beat['questions'] ?? [])
                    ->filter(fn (array $question) => in_array($this->resident->id, $question['residents'] ?? [], true) && ! $run->questionMet($question['id']))
                    ->map(fn (array $question) => ['text' => $question['text'], 'run' => $run, 'beat' => $beat['id'], 'question' => $question['id']])));

        return $beatQuestions->merge($this->offerQuestions($runs))
            ->groupBy('text')
            ->map(fn (Collection $entries) => $entries->map(fn (array $entry) => ['run' => $entry['run'], 'beat' => $entry['beat'], 'question' => $entry['question']])->all());
    }

    /**
     * @param  Collection<int, WorldSessionQuest>  $runs
     * @return Collection<int, array{text: string, run: WorldSessionQuest, beat: null, question: string}>
     */
    private function offerQuestions(Collection $runs): Collection
    {
        $pending = QuestOffer::where('world_session_id', $this->session->id)->where('status', QuestOfferStatus::Pending)->pluck('world_session_quest_id')->all();
        $status = app(OfferQuestionStatus::class);

        return $runs->where('status', QuestStatus::Available)
            ->filter(fn (WorldSessionQuest $run) => $run->quest->giverId() === $this->resident->id
                && $run->quest->offerQuestion() !== null
                && ! in_array($run->id, $pending, true)
                && ! $status->handle($this->session, $run->quest)['met'])
            ->map(fn (WorldSessionQuest $run) => ['text' => $run->quest->offerQuestion(), 'run' => $run, 'beat' => null, 'question' => Quest::OFFER_QUESTION_ID])
            ->values();
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
