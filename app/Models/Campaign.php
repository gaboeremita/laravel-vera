<?php

namespace App\Models;

use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of a world's quests, with its own rubric for the ending
 * written once all of them are over.
 */
#[Fillable(['world_id', 'key', 'title', 'definition'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['definition' => 'array'];
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }

    public function description(): string
    {
        return (string) ($this->definition['description'] ?? '');
    }

    /**
     * @return array{guidance?: string, dimensions: array<int, array{name: string, description?: string}>, tiers?: array<int, string>}
     */
    public function rubric(): array
    {
        return $this->definition['rubric'] ?? ['dimensions' => []];
    }
}
