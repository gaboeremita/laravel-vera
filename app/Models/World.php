<?php

namespace App\Models;

use App\Enums\AssistantKind;
use Database\Factories\WorldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['name', 'slug', 'description', 'assistant_context_prompt', 'npc_context_prompt', 'spawn_region_id', 'spawn_passage_id', 'narrator_model_id', 'review_reveals'])]
class World extends Model
{
    /** @use HasFactory<WorldFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['review_reveals' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'world_user')
            ->using(WorldUser::class)
            ->withTimestamps();
    }

    public function worldUsers(): HasMany
    {
        return $this->hasMany(WorldUser::class);
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(WorldResident::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function startingInventories(): HasMany
    {
        return $this->hasMany(StartingInventory::class);
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function narratorModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'narrator_model_id');
    }

    public function spawnRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'spawn_region_id');
    }

    public function cardImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('role', 'card');
    }

    public function portraitImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('role', 'portrait');
    }

    public function contextPromptFor(AssistantKind $kind): string
    {
        return $kind === AssistantKind::WorldNpc ? $this->npc_context_prompt : $this->assistant_context_prompt;
    }

    /**
     * The spawn passage, or null while none is chosen or it no longer exists in its region.
     *
     * @return ?array{id: string, name: string, position: array{x: float, y: float, z: float}, facing: float, radius: float, arrival: array{x: float, y: float, z: float}, zoneId: ?string}
     */
    public function spawnPassage(): ?array
    {
        if ($this->spawn_passage_id === null) {
            return null;
        }

        return $this->spawnRegion?->passage($this->spawn_passage_id);
    }

    protected static function booted(): void
    {
        static::deleting(function (World $world): void {
            $world->regions->each->delete();
        });
    }
}
