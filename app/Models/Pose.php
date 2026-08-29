<?php

namespace App\Models;

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
        'vrm_blendshapes',
    ];

    protected function casts(): array
    {
        return [
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
