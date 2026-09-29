<?php

namespace App\Actions\Quests;

use App\Events\Quests\QuestsUpdated;
use App\Models\WorldSessionQuest;

/**
 * Tells the player's page which runs changed, as they see them, and what to announce.
 */
class BroadcastQuestRuns
{
    public function __construct(private readonly PlayerRunView $playerRunView) {}

    /**
     * @param  iterable<WorldSessionQuest>  $runs
     * @param  array<int, array<string, mixed>>  $notices  each with at least a type, the quest title and a text
     */
    public function handle(int $sessionId, iterable $runs, array $notices = []): void
    {
        $views = collect($runs)->unique('id')->map(fn (WorldSessionQuest $run) => $this->playerRunView->handle($run->loadMissing('quest')))->values()->all();
        if ($views === [] && $notices === []) {
            return;
        }

        QuestsUpdated::dispatch($sessionId, $views, $notices);
    }

    /**
     * @return array{type: string, questTitle: string, text: string}
     */
    public static function notice(string $type, WorldSessionQuest $run, string $text = ''): array
    {
        return ['type' => $type, 'questTitle' => $run->quest->title, 'text' => $text];
    }
}
