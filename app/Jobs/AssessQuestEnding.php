<?php

namespace App\Jobs;

use App\Actions\Quests\AssessEnding;
use App\Actions\Quests\CheckCampaignEnded;
use App\Actions\Quests\RecordQuestEvent;
use App\Actions\Quests\SyncSessionQuests;
use App\Enums\EndingStatus;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestEndingReady;
use App\Events\Quests\QuestFlagChanged;
use App\Models\WorldSessionQuest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writes a run's ending once it has completed, failed or been abandoned.
 */
class AssessQuestEnding implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 180;

    public function __construct(public int $runId) {}

    public function handle(AssessEnding $assessEnding, RecordQuestEvent $recordQuestEvent, SyncSessionQuests $syncSessionQuests, CheckCampaignEnded $checkCampaignEnded): void
    {
        $run = WorldSessionQuest::with(['quest', 'worldSession.worldUser.world'])->find($this->runId);
        if ($run === null) {
            return;
        }

        try {
            $ending = $assessEnding->forRun($run);
        } catch (Throwable $exception) {
            report($exception);
            // A failure in one attempt waits for the next; only the last marks the ending failed.
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff);

                return;
            }
            $this->fail($exception);

            return;
        }

        $run->update(['ending' => $ending, 'ending_status' => EndingStatus::Written]);
        $recordQuestEvent->handle($run, QuestEventType::EndingWritten, payload: ['title' => $ending['title'], 'tier' => $ending['tier'], 'resultingFlags' => $ending['resultingFlags']]);
        QuestEndingReady::dispatch($run->world_session_id, ['runId' => $run->id, 'title' => $run->quest->title, 'endingStatus' => EndingStatus::Written->value, 'ending' => self::view($ending)]);

        if ($ending['resultingFlags'] !== []) {
            QuestFlagChanged::dispatch($run->world_session_id);
        }
        $syncSessionQuests->handle($run->worldSession);
        $checkCampaignEnded->handle($run);

        if ($run->quest->reward() !== null && $run->status === QuestStatus::Completed) {
            GiveQuestReward::dispatch($run->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $run = WorldSessionQuest::with('quest')->find($this->runId);
        if ($run === null) {
            return;
        }

        $run->update(['ending_status' => EndingStatus::Failed]);
        app(RecordQuestEvent::class)->handle($run, QuestEventType::EndingFailed, payload: ['error' => $exception?->getMessage() ?? 'unknown']);
        QuestEndingReady::dispatch($run->world_session_id, ['runId' => $run->id, 'title' => $run->quest->title, 'endingStatus' => EndingStatus::Failed->value, 'ending' => null]);
        app(CheckCampaignEnded::class)->handle($run);
    }

    /**
     * An ending as the player sees it.
     *
     * @param  array<string, mixed>  $ending
     * @return array{tier: ?string, title: string, epilogue: string, scores: array<int, array<string, mixed>>}
     */
    public static function view(array $ending): array
    {
        return ['tier' => $ending['tier'] ?? null, 'title' => $ending['title'] ?? '', 'epilogue' => $ending['epilogue'] ?? '', 'scores' => $ending['scores'] ?? []];
    }
}
