<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\BroadcastQuestRuns;
use App\Contracts\AgentTool;
use App\Models\Quest;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * A quest tool the creator directs through a character. Every change it makes
 * is logged as the creator's, so endings know it didn't happen in the story.
 */
abstract class CreatorQuestTool implements AgentTool
{
    public function __construct(protected readonly WorldSession $session) {}

    /**
     * @return array<string, mixed>
     */
    protected function questParameter(): array
    {
        return ['type' => 'string', 'enum' => $this->quests()->keys()->values()->all(), 'description' => 'The quest, by title.'];
    }

    /**
     * @return Collection<string, Quest>
     */
    protected function quests(): Collection
    {
        return $this->session->worldUser->world->quests()->orderBy('title')->get()->keyBy('title');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function quest(array $arguments): Quest
    {
        return $this->quests()->get(trim((string) ($arguments['quest'] ?? ''))) ?? throw new RuntimeException('There is no quest with that title.');
    }

    protected function latestRun(Quest $quest): ?WorldSessionQuest
    {
        return $this->session->questRuns()->where('quest_id', $quest->id)->latest('run')->first()?->setRelation('quest', $quest);
    }

    protected function broadcast(WorldSessionQuest $run): void
    {
        app(BroadcastQuestRuns::class)->handle($this->session->id, [$run->fresh('quest')]);
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
