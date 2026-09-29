<?php

namespace App\Actions\Quests;

use App\Enums\EndingStatus;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestEnded;
use App\Events\Quests\QuestStateChanged;
use App\Jobs\AssessQuestEnding;
use App\Models\WorldSessionQuest;

/**
 * Ends a run as completed, failed or abandoned, and has its ending written.
 */
class EndQuestRun
{
    public function __construct(private readonly RecordQuestEvent $recordQuestEvent) {}

    /**
     * @param  array<string, mixed>  $payload  what caused it
     */
    public function handle(WorldSessionQuest $run, QuestStatus $status, array $payload = [], bool $byCreator = false): void
    {
        $run->update(['status' => $status, 'ended_at' => now(), 'ending_status' => EndingStatus::Pending]);
        $type = match ($status) {
            QuestStatus::Completed => QuestEventType::Completed,
            QuestStatus::Failed => QuestEventType::Failed,
            default => QuestEventType::Abandoned,
        };
        $this->recordQuestEvent->handle($run, $type, payload: $payload, byCreator: $byCreator);

        QuestEnded::dispatch($run->world_session_id, $run->id);
        QuestStateChanged::dispatch($run->world_session_id, "\"{$run->quest->title}\" {$status->value}");
        AssessQuestEnding::dispatch($run->id)->afterCommit();
    }
}
