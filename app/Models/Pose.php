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
    /**
     * Poses the world plays for her (walking, greeting someone who starts a conversation, talking while she speaks), which are never hers to choose.
     */
    public const WORLD_MOTION_NAMES = ['walk', 'walking', 'walk-cycle', 'walk_cycle', 'walk cycle', 'walk-start', 'walk_start', 'walk start', 'walk-stop', 'walk_stop', 'walk stop', 'greeting', 'greet', 'talk', 'talking', 'swim', 'swimming', 'swim-to-edge', 'swim_to_edge', 'swim to edge', 'swimming-to-edge', 'swimming_to_edge', 'swimming to edge'];

    /**
     * Whether she picks this pose herself: everything except her idle default
     * and the motions the world plays on its own.
     */
    public function isChosen(): bool
    {
        $name = mb_strtolower(trim($this->name));

        return $name !== 'default' && ! in_array($name, self::WORLD_MOTION_NAMES, true);
    }

    /** @use HasFactory<PoseFactory> */
    use HasFactory, HasNormalizedBlendshapes;

    protected $fillable = [
        'name',
        'posture',
        'vrm_blendshapes',
        'restricted',
        'hold',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'posture' => 'standing',
        'restricted' => false,
        'hold' => false,
    ];

    protected function casts(): array
    {
        return [
            'posture' => Posture::class,
            'vrm_blendshapes' => 'array',
            'restricted' => 'boolean',
            'hold' => 'boolean',
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
