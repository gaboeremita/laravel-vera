<?php

namespace App\Models;

use App\Enums\AssistantKind;
use Database\Factories\WorldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(WorldResident::class);
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
