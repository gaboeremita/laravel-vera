<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'disk',
        'mime_type',
        'size',
        'original_name',
        'video_gen_model_id',
        'status',
        'job_id',
        'prompt',
        'duration',
        'aspect_ratio',
        'generate_audio',
        'first_frame_image_id',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => VideoStatus::class,
            'generate_audio' => 'boolean',
        ];
    }

    public function videoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VideoGenModel::class, 'video_gen_model_id');
    }

    public function firstFrame(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'first_frame_image_id');
    }

    /**
     * Get the full accessible URL for this video, or null while a generated video has no file yet.
     */
    public function getUrlAttribute(): ?string
    {
        return $this->path === null ? null : Storage::disk($this->disk)->url($this->path);
    }

    /**
     * @return array{id: int, status: string, url: ?string, prompt: ?string, duration: ?int, aspect_ratio: ?string, failure_reason: ?string}
     */
    public function toChatPayload(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'url' => $this->url,
            'prompt' => $this->prompt,
            'duration' => $this->duration,
            'aspect_ratio' => $this->aspect_ratio,
            'failure_reason' => $this->failure_reason,
        ];
    }

    /**
     * The line the assistant reads about this video in later turns.
     */
    public function historyNote(): string
    {
        $state = match ($this->status) {
            VideoStatus::Queued, VideoStatus::Generating => 'generating',
            VideoStatus::Completed => 'ready',
            VideoStatus::Failed => "failed: {$this->failure_reason}",
        };

        return "[Video: \"{$this->prompt}\" — {$state}]";
    }

    /**
     * Move a downloaded video file onto the public disk and record it.
     */
    public function storeDownloaded(string $tempPath, string $storagePath): void
    {
        $path = "{$storagePath}/".Str::uuid().'.mp4';

        Storage::disk('public')->put($path, fopen($tempPath, 'r'));

        $this->fill([
            'path' => $path,
            'disk' => 'public',
            'mime_type' => 'video/mp4',
            'size' => filesize($tempPath),
        ]);
    }
}
