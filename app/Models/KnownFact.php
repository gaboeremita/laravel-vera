<?php

namespace App\Models;

use App\Enums\RevealSource;
use Database\Factories\KnownFactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['world_session_id', 'fact_id', 'source', 'source_name', 'summary'])]
class KnownFact extends Model
{
    /** @use HasFactory<KnownFactFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['source' => RevealSource::class];
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(Fact::class);
    }

    /**
     * @return array{factId: int, topic: string, summary: string, sourceName: string, learnedAt: string}
     */
    public function toPayload(): array
    {
        return [
            'factId' => $this->fact_id,
            'topic' => $this->fact->topic,
            'summary' => $this->summary,
            'sourceName' => $this->source_name,
            'learnedAt' => $this->created_at->toIso8601String(),
        ];
    }
}
