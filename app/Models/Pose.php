<?php

namespace App\Models;

use App\Enums\Posture;
use App\Models\Concerns\HasNormalizedBlendshapes;
use Database\Factories\PoseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pose extends Model
{
    /** @use HasFactory<PoseFactory> */
    use HasFactory, HasNormalizedBlendshapes;

    protected $fillable = [
        'name',
        'posture',
        'vrm_blendshapes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'posture' => 'standing',
    ];

    protected function casts(): array
    {
        return [
            'posture' => Posture::class,
            'vrm_blendshapes' => 'array',
        ];
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function animationFile(): HasOne
    {
        return $this->hasOne(PoseAnimationFile::class);
    }
}
