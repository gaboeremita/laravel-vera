<?php

namespace App\Jobs;

use App\Actions\Quests\JudgeQuestionAction;
use App\Actions\Quests\RecordQuestEvent;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestQuestionJudged;
use App\Models\Conversation;
use App\Models\WorldResident;
use App\Models\WorldSessionQuest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Checks a question a resident signalled. Only one check per run and
 * question runs at a time; a signal while one is waiting adds nothing.
 */
class JudgeQuestion implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(public int $runId, public string $questionId, public int $conversationId, public int $residentId) {}

    public function uniqueId(): string
    {
        return "{$this->runId}:{$this->questionId}";
    }

    public function handle(JudgeQuestionAction $judgeQuestion, RecordQuestEvent $recordQuestEvent): void
    {
        $run = WorldSessionQuest::with(['quest', 'worldSession.worldUser.world'])->find($this->runId);
        $conversation = Conversation::find($this->conversationId);
        $question = $run?->quest->question($this->questionId);
        if ($run === null || $conversation === null || $question === null) {
            return;
        }

        try {
            $answer = $judgeQuestion->handle($run, $question, $conversation, WorldResident::with('assistant')->find($this->residentId)?->assistant->name ?? 'The resident');
        } catch (Throwable $exception) {
            report($exception);
            $recordQuestEvent->handle($run, QuestEventType::QuestionJudged, payload: ['question' => $this->questionId, 'met' => false, 'error' => $exception->getMessage()]);

            return;
        }

        $recordQuestEvent->handle($run, QuestEventType::QuestionJudged, payload: ['question' => $this->questionId, ...$answer, 'conversationId' => $this->conversationId]);

        $run->refresh();
        if ($answer['met'] && $run->status === QuestStatus::Active) {
            $run->mergeState(['questions' => [...($run->state['questions'] ?? []), $this->questionId => true]]);
            $run->save();
            QuestQuestionJudged::dispatch($run->world_session_id);
        }
    }
}
