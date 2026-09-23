<?php

namespace App\Models;

use App\Enums\Posture;
use Database\Factories\WorldSessionResidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldSessionResident extends Model
{
    /** @use HasFactory<WorldSessionResidentFactory> */
    use HasFactory;

    protected $fillable = [
        'world_session_id',
        'world_resident_id',
        'position',
        'rotation',
        'spot_id',
        'activity_id',
        'posture',
        'exit_position',
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
            'position' => 'array',
            'rotation' => 'array',
            'posture' => Posture::class,
            'exit_position' => 'array',
        ];
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function worldResident(): BelongsTo
    {
        return $this->belongsTo(WorldResident::class);
    }
}
