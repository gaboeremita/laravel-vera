<?php

namespace App\Actions\Quests;

use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestStarted;
use App\Events\Quests\QuestStateChanged;
use App\Models\WorldSessionQuest;

/**
 * Makes a run active and has its conditions checked straight away.
 */
class StartQuestRun
{
    public function __construct(private readonly RecordQuestEvent $recordQuestEvent) {}

    /**
     * @param  string  $cause  what started it: session, condition, offer or creator
     * @param  array<string, mixed>  $payload  more about what started it
     */
    public function handle(WorldSessionQuest $run, string $cause, bool $byCreator = false, array $payload = []): void
    {
        $run->update(['status' => QuestStatus::Active, 'started_at' => now()]);
        $this->recordQuestEvent->handle($run, QuestEventType::Started, payload: ['cause' => $cause, ...$payload], byCreator: $byCreator);

        QuestStarted::dispatch($run->world_session_id, $run->id);
        QuestStateChanged::dispatch($run->world_session_id, "\"{$run->quest->title}\" started");
    }
}
