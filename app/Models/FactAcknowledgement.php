<?php

namespace App\Models;

use Database\Factories\FactAcknowledgementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['world_session_id', 'fact_id', 'world_resident_id'])]
class FactAcknowledgement extends Model
{
    /** @use HasFactory<FactAcknowledgementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(Fact::class);
    }

    public function worldResident(): BelongsTo
    {
        return $this->belongsTo(WorldResident::class);
    }
}
