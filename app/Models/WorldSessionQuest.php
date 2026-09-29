<?php

namespace App\Models;

use App\Enums\EndingStatus;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use Database\Factories\WorldSessionQuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of a quest in a session. A repeatable quest can have several.
 */
#[Fillable(['world_session_id', 'quest_id', 'run', 'status', 'state', 'ending', 'ending_status', 'started_at', 'ended_at'])]
class WorldSessionQuest extends Model
{
    /** @use HasFactory<WorldSessionQuestFactory> */
    use HasFactory;

    public const EMPTY_STATE = ['finishedBeats' => [], 'flags' => [], 'seen' => [], 'questions' => []];

    protected $attributes = ['state' => '{"finishedBeats":[],"flags":[],"seen":[],"questions":[]}'];

    protected function casts(): array
    {
        return [
            'run' => 'integer',
            'status' => QuestStatus::class,
            'state' => 'array',
            'ending' => 'array',
            'ending_status' => EndingStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuestEvent::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [QuestStatus::Available, QuestStatus::Active], true);
    }

    /**
     * @return array<int, string>
     */
    public function finishedBeats(): array
    {
        return $this->state['finishedBeats'] ?? [];
    }

    public function hasFinished(string $beatId): bool
    {
        return in_array($beatId, $this->finishedBeats(), true);
    }

    public function hasFlag(string $flag): bool
    {
        return array_key_exists($flag, $this->state['flags'] ?? []);
    }

    public function questionMet(string $questionId): bool
    {
        return (bool) ($this->state['questions'][$questionId] ?? false);
    }

    public function hasSeen(string $leafKey): bool
    {
        return in_array($leafKey, $this->state['seen'] ?? [], true);
    }

    /**
     * The beats whose requirements are finished and that aren't finished
     * themselves, while the run is active.
     *
     * @return array<int, array<string, mixed>>
     */
    public function currentBeats(): array
    {
        if ($this->status !== QuestStatus::Active) {
            return [];
        }

        return collect($this->quest->beats())
            ->reject(fn (array $beat) => $this->hasFinished($beat['id']))
            ->filter(fn (array $beat) => collect($beat['requires'] ?? [])->every(fn (string $required) => $this->hasFinished($required)))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function mergeState(array $changes): void
    {
        $this->state = [...self::EMPTY_STATE, ...($this->state ?? []), ...$changes];
    }

    /**
     * The residents who remember how the run ended: the giver, residents the
     * definition names, and residents who granted a flag or signalled a
     * question in it.
     *
     * @return array<int, int>
     */
    public function involvedResidentIds(): array
    {
        return collect($this->quest->namedResidentIds())
            ->merge(collect($this->state['flags'] ?? [])->pluck('by'))
            ->merge($this->events()->where('type', QuestEventType::QuestionSignalled)->get()->pluck('payload.residentId'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function resultingFlags(): array
    {
        return $this->ending['resultingFlags'] ?? [];
    }
}
