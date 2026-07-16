<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_id', 'name', 'endpoint', 'thinking_key', 'prompt', 'config', 'additional_config'])]
class AiModel extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'additional_config' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
