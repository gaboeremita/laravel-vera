<?php

namespace App\Models;

use App\Enums\AssistantMode;
use Database\Factories\AssistantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'prompt', 'opening_message', 'archive_id', 'mode', 'agent_config'])]
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
}
