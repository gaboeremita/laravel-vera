<?php

namespace App\Models;

use Database\Factories\VideoGenModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_id', 'name', 'endpoint', 'prompt', 'config', 'additional_config'])]
class VideoGenModel extends Model
{
    /** @use HasFactory<VideoGenModelFactory> */
    use HasFactory;

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
        return $this->belongsTo(VideoGenProvider::class, 'provider_id');
    }
}
