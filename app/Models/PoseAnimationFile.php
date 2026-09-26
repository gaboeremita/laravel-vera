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

    /**
     * Deletes a stored animation once no pose refers to it any more. Poses
     * copied between assistants share one stored file, so a file outlives the
     * pose it was uploaded for while another pose still plays it.
     */
    public static function releaseStorage(string $disk, string $path): void
    {
        if (! static::where('disk', $disk)->where('path', $path)->exists()) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
