<?php

namespace App\Models;

use Database\Factories\AiModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_id', 'name', 'endpoint', 'thinking_key', 'prompt', 'config', 'additional_config', 'supports_tools', 'cache_marks', 'conversation_key_field'])]
class AiModel extends Model
{
    /** @use HasFactory<AiModelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'additional_config' => 'array',
            'supports_tools' => 'boolean',
            'cache_marks' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
