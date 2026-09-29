<?php

namespace App\Models;

use Database\Factories\QuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quest written for a world. Everything but its key, title and campaign
 * lives in `definition`, whose shape is described in research R2 of the
 * quests feature.
 */
#[Fillable(['world_id', 'campaign_id', 'key', 'title', 'definition'])]
class Quest extends Model
{
    /** @use HasFactory<QuestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['definition' => 'array'];
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorldSessionQuest::class);
    }

    public function description(): string
    {
        return (string) ($this->definition['description'] ?? '');
    }

    public function startMode(): string
    {
        return $this->definition['start']['mode'] ?? 'auto';
    }

    public function giverId(): ?int
    {
        return $this->startMode() === 'offer' ? (int) $this->definition['start']['giver'] : null;
    }

    public function isRepeatable(): bool
    {
        return (bool) ($this->definition['repeatable'] ?? false);
    }

    /**
     * @return array<int, array{quest?: string, campaign?: string, outcome: string}>
     */
    public function requirements(): array
    {
        return $this->definition['requires'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function beats(): array
    {
        return $this->definition['beats'] ?? [];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function beat(string $beatId): ?array
    {
        return collect($this->beats())->firstWhere('id', $beatId);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function question(string $questionId): ?array
    {
        return collect($this->beats())->flatMap(fn (array $beat) => $beat['questions'] ?? [])->firstWhere('id', $questionId);
    }

    /**
     * @return array{guidance?: string, dimensions: array<int, array{name: string, description?: string}>, tiers?: array<int, string>}
     */
    public function rubric(): array
    {
        return $this->definition['rubric'] ?? ['dimensions' => []];
    }

    /**
     * The world residents the definition names anywhere: its giver and those
     * on beats' knowledge, grants and questions.
     *
     * @return array<int, int>
     */
    public function namedResidentIds(): array
    {
        return collect($this->beats())
            ->flatMap(fn (array $beat) => [
                ...collect($beat['knowledge'] ?? [])->pluck('resident'),
                ...collect($beat['grants'] ?? [])->pluck('resident'),
                ...collect($beat['questions'] ?? [])->pluck('residents')->flatten(),
            ])
            ->push($this->giverId())
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
