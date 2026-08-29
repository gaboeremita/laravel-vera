<?php

namespace App\Models;

use Database\Factories\EmotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Emotion extends Model
{
    /** @use HasFactory<EmotionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'restricted',
        'vrm_blendshapes',
    ];

    protected function casts(): array
    {
        return [
            'vrm_blendshapes' => 'array',
        ];
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function video(): MorphOne
    {
        return $this->morphOne(Video::class, 'videoable');
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    /**
     * Converts {expression, weight} pairs (weight as a 0-100 percentage
     * from the UI) into the 0.0-1.0 scale VRM's expressionManager expects.
     *
     * @param  array<int, array{expression: string, weight: float}>|null  $blendshapes
     * @return array<int, array{expression: string, weight: float}>|null
     */
    public static function normalizeBlendshapes(?array $blendshapes): ?array
    {
        if ($blendshapes === null) {
            return null;
        }

        return array_map(fn (array $b) => [
            'expression' => $b['expression'],
            'weight' => round($b['weight'] / 100, 4),
        ], $blendshapes);
    }
}
