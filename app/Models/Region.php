<?php

namespace App\Models;

use App\Enums\AssistantKind;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

#[Fillable(['world_id', 'name', 'slug', 'description', 'environment_disk', 'environment_path', 'environment_original_name', 'assistant_context_prompt', 'npc_context_prompt', 'settings', 'layout'])]
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['settings' => 'array', 'layout' => 'array'];
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
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

    public function passageLinks(): HasMany
    {
        return $this->hasMany(PassageLink::class);
    }

    /**
     * @return ?array{id: string, name: string, position: array{x: float, y: float, z: float}, facing: float, radius: float, arrival: array{x: float, y: float, z: float}, zoneId: ?string}
     */
    public function passage(string $passageId): ?array
    {
        return collect($this->layout['passages'] ?? [])->firstWhere('id', $passageId);
    }

    public function contextPromptFor(AssistantKind $kind): string
    {
        return $kind === AssistantKind::WorldNpc ? $this->npc_context_prompt : $this->assistant_context_prompt;
    }

    protected static function booted(): void
    {
        static::deleted(function (Region $region): void {
            Storage::disk($region->environment_disk)->delete($region->environment_path);
        });
    }
}
