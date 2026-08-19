<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_id', 'name', 'endpoint', 'prompt', 'config', 'additional_config'])]
class ImageGenModel extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'additional_config' => 'array',
            'prompt' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ImageGenProvider::class, 'provider_id');
    }
}
