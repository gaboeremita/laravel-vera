<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\RecordQuestEvent;
use App\Contracts\AgentTool;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Jobs\JudgeQuestion;
use App\Models\Conversation;
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
            app(RecordQuestEvent::class)->handle($run, QuestEventType::QuestionSignalled, $beatId, ['question' => $questionId, 'reason' => $reason, 'residentId' => $this->resident->id, 'residentName' => $this->resident->assistant->name, 'conversationId' => $this->conversation->id]);
            JudgeQuestion::dispatch($run->id, $questionId, $this->conversation->id, $this->resident->id);
        }

        return ['status' => 'signalled', 'note' => 'Noted; carry on in character.'];
    }

    /**
     * The unmet questions of current beats that name this resident, by text.
     *
     * @return Collection<string, array<int, array{run: WorldSessionQuest, beat: string, question: string}>>
     */
    public function signallable(): Collection
    {
        return $this->session->questRuns()->with('quest')->where('status', QuestStatus::Active)->get()
            ->flatMap(fn (WorldSessionQuest $run) => collect($run->currentBeats())
                ->flatMap(fn (array $beat) => collect($beat['questions'] ?? [])
                    ->filter(fn (array $question) => in_array($this->resident->id, $question['residents'] ?? [], true) && ! $run->questionMet($question['id']))
                    ->map(fn (array $question) => ['text' => $question['text'], 'run' => $run, 'beat' => $beat['id'], 'question' => $question['id']])))
            ->groupBy('text')
            ->map(fn (Collection $entries) => $entries->map(fn (array $entry) => ['run' => $entry['run'], 'beat' => $entry['beat'], 'question' => $entry['question']])->all());
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
