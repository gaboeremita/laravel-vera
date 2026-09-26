<?php

namespace App\Models;

use Database\Factories\VrmFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class VrmFile extends Model
{
    /** @use HasFactory<VrmFileFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'lod_path',
        'disk',
        'mime_type',
        'size',
        'lod_size',
        'original_name',
    ];

    public function vrmable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * The low-detail version of the model, drawn when she is far from the
     * user; null when there is none.
     */
    public function getLodUrlAttribute(): ?string
    {
        return $this->lod_path !== null ? Storage::disk($this->disk)->url($this->lod_path) : null;
    }

    /**
     * Deletes the low-detail version. It is made from the model it sits next
     * to, so it goes whenever that model is replaced or removed.
     */
    public function forgetLod(): void
    {
        if ($this->lod_path === null) {
            return;
        }
        Storage::disk($this->disk)->delete($this->lod_path);
        $this->update(['lod_path' => null, 'lod_size' => null]);
    }
}
