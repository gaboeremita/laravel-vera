<?php

namespace App\Models;

use App\Enums\RevealSource;
use Database\Factories\RevealAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['world_session_id', 'fact_id', 'world_resident_id', 'fact_topic', 'holder_name', 'source', 'reason', 'reviewed', 'approved', 'verdict'])]
class RevealAttempt extends Model
{
    /** @use HasFactory<RevealAttemptFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['source' => RevealSource::class, 'reviewed' => 'boolean', 'approved' => 'boolean'];
    }

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
