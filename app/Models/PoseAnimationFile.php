<?php

namespace App\Models;

use Database\Factories\PoseAnimationFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PoseAnimationFile extends Model
{
    /** @use HasFactory<PoseAnimationFileFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'disk',
        'mime_type',
        'size',
        'original_name',
    ];

    public function pose(): BelongsTo
    {
        return $this->belongsTo(Pose::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
