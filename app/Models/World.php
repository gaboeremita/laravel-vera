<?php

namespace App\Models;

use App\Enums\AssistantKind;
use Database\Factories\WorldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'slug', 'description', 'environment_disk', 'environment_path', 'environment_original_name', 'assistant_context_prompt', 'npc_context_prompt', 'settings'])]
class World extends Model
{
    /** @use HasFactory<WorldFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['settings' => 'array'];
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

    public function residents(): HasMany
    {
        return $this->hasMany(WorldResident::class);
    }

    public function cardImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('role', 'card');
    }

    public function portraitImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('role', 'portrait');
    }

    public function track(): MorphOne
    {
        return $this->morphOne(Track::class, 'trackable');
    }

    public function contextPromptFor(AssistantKind $kind): string
    {
        return $kind === AssistantKind::WorldNpc ? $this->npc_context_prompt : $this->assistant_context_prompt;
    }

    protected static function booted(): void
    {
        static::deleted(function (World $world): void {
            Storage::disk($world->environment_disk)->delete($world->environment_path);
        });
    }
}
