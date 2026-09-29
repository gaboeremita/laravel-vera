<?php

namespace App\Exceptions;

use App\Models\Quest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Something can't be deleted while quests name it, since their conditions
 * would silently change meaning.
 */
class UsedByQuests extends RuntimeException
{
    /**
     * @param  Collection<int, Quest>  $quests
     */
    public function __construct(string $what, public readonly Collection $quests)
    {
        parent::__construct(sprintf('%s is used by %s: %s. Change %s first.', $what, $quests->count() === 1 ? 'a quest' : 'quests', $quests->pluck('title')->implode(', '), $quests->count() === 1 ? 'it' : 'them'));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'quests' => $this->quests->map(fn (Quest $quest) => ['id' => $quest->id, 'title' => $quest->title])->values(),
        ], 422);
    }
}
