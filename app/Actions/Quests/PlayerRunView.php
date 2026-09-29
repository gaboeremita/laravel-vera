<?php

namespace App\Actions\Quests;

use App\Models\WorldSessionQuest;

/**
 * A run as the player sees it. Hidden beats stay out until they finish, so
 * they never reach the browser early.
 */
class PlayerRunView
{
    /**
     * @return array{id: int, questId: int, key: string, title: string, description: string, campaignId: ?int, run: int, status: string, beats: array<int, array{id: string, text: string, finished: bool, current: bool}>, ending: ?array<string, mixed>, endingStatus: ?string, reward: ?array{giverName: string, line: string, credits: int, items: array<int, array{name: string, quantity: int}>}, startedAt: ?string, endedAt: ?string}
     */
    public function handle(WorldSessionQuest $run): array
    {
        $quest = $run->quest;
        $currentIds = collect($run->currentBeats())->pluck('id')->all();

        return [
            'id' => $run->id,
            'questId' => $quest->id,
            'key' => $quest->key,
            'title' => $quest->title,
            'description' => $quest->description(),
            'campaignId' => $quest->campaign_id,
            'run' => $run->run,
            'status' => $run->status->value,
            'beats' => collect($quest->beats())
                ->filter(fn (array $beat) => ! ($beat['hidden'] ?? false) || $run->hasFinished($beat['id']))
                ->map(fn (array $beat) => [
                    'id' => $beat['id'],
                    'text' => $beat['text'],
                    'finished' => $run->hasFinished($beat['id']),
                    'current' => in_array($beat['id'], $currentIds, true),
                ])
                ->values()
                ->all(),
            'ending' => $run->ending === null ? null : [
                'tier' => $run->ending['tier'] ?? null,
                'title' => $run->ending['title'] ?? '',
                'epilogue' => $run->ending['epilogue'] ?? '',
                'scores' => $run->ending['scores'] ?? [],
            ],
            'endingStatus' => $run->ending_status?->value,
            'reward' => $run->state['reward'] ?? null,
            'startedAt' => $run->started_at?->toIso8601String(),
            'endedAt' => $run->ended_at?->toIso8601String(),
        ];
    }
}
