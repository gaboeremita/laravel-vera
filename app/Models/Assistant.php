<?php

namespace App\Models;

use App\Enums\AssistantMode;
use App\Enums\AssistantPortraitType;
use Database\Factories\AssistantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['name', 'slug', 'description', 'prompt', 'opening_message', 'archive_id', 'mode', 'agent_config', 'portrait_type'])]
class Assistant extends Model
{
    /** @use HasFactory<AssistantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'prompt' => 'array',
            'mode' => AssistantMode::class,
            'agent_config' => 'array',
            'portrait_type' => AssistantPortraitType::class,
        ];
    }

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(AssistantUser::class)
            ->withTimestamps();
    }

    public function emotions(): HasMany
    {
        return $this->hasMany(Emotion::class);
    }

    public function vrm(): MorphOne
    {
        return $this->morphOne(VrmFile::class, 'vrmable');
    }

    /**
     * @return array{regular: array<string>, intimate: array<string>}
     */
    public function promptEmotionNames(): array
    {
        if ($this->portrait_type === AssistantPortraitType::Avatar3d) {
            // 3D avatar assistants don't have uploaded Emotion records to draw
            // from — this list must stay in sync with the blendshape mapping
            // in resources/js/utils/vrmExpressions.js.
            return [
                'regular' => ['happy', 'sad', 'annoyed', 'flustered', 'surprised', 'angry', 'relaxed'],
                'intimate' => ['seduced'],
            ];
        }

        return [
            'regular' => $this->emotions()->where('restricted', false)->pluck('name')->toArray(),
            'intimate' => $this->emotions()->where('restricted', true)->pluck('name')->toArray(),
        ];
    }
}
