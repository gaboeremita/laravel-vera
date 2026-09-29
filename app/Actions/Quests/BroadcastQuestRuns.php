<?php

namespace App\Actions\Quests;

use App\Enums\QuestStatus;
use App\Events\Quests\QuestsUpdated;
use App\Models\WorldSessionQuest;

/**
 * Tells the player's page which runs changed, as they see them, and what to announce.
 * A quest nobody has offered yet reaches the page as its id and status only,
 * so the page can drop it without learning what it is.
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
        $views = collect($runs)->unique('id')
            ->map(fn (WorldSessionQuest $run) => $run->status === QuestStatus::Available
                ? ['id' => $run->id, 'status' => $run->status->value]
                : $this->playerRunView->handle($run->loadMissing('quest')))
            ->values()
            ->all();
        $notices = array_values(array_filter($notices, fn (array $notice) => $notice['type'] !== 'questAvailable'));
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
