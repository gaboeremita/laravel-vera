<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\RecordQuestEvent;
use App\Actions\Quests\StartQuestRun;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Models\WorldSessionQuest;
use RuntimeException;

class ResetQuestTool extends CreatorQuestTool
{
    public function name(): string
    {
        return 'reset_quest';
    }

    public function description(): string
    {
        return 'Clears the latest run of a quest back to how it starts, when the creator asks. Its log keeps everything that happened before.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => ['quest' => $this->questParameter()], 'required' => ['quest']];
    }

    public function handle(array $arguments): array
    {
        $quest = $this->quest($arguments);
        $run = $this->latestRun($quest) ?? throw new RuntimeException('That quest has no run to reset.');

        $run->update(['status' => QuestStatus::Available, 'state' => WorldSessionQuest::EMPTY_STATE, 'ending' => null, 'ending_status' => null, 'started_at' => null, 'ended_at' => null]);
        app(RecordQuestEvent::class)->handle($run, QuestEventType::Reset, byCreator: true);
        if ($quest->campaign_id !== null) {
            $this->session->campaignEndings()->where('campaign_id', $quest->campaign_id)->delete();
        }
        if ($quest->startMode() === 'auto') {
            app(StartQuestRun::class)->handle($run, 'creator', byCreator: true);
        }
        $this->broadcast($run);

        return ['status' => 'reset'];
    }
}
