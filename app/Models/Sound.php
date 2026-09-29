<?php

namespace App\Models;

use Database\Factories\SoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A sound file stored once per content hash, so anything that uses the same
 * sound shares one file and one browser cache entry.
 */
#[Fillable(['hash', 'disk', 'path', 'mime_type', 'size', 'original_name'])]
class Sound extends Model
{
    /** @use HasFactory<SoundFactory> */
    use HasFactory;

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
