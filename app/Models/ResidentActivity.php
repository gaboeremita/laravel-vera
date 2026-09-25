<?php

namespace App\Models;

use Database\Factories\ResidentActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['world_session_id', 'world_resident_id', 'source', 'verb', 'target', 'activity', 'reason', 'narration', 'zone_id', 'outcome', 'outcome_reason', 'finished_at'])]
class ResidentActivity extends Model
{
    /** @use HasFactory<ResidentActivityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['finished_at' => 'datetime'];
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
