<?php

namespace App\Models;

use App\Enums\QuestEventType;
use Database\Factories\QuestEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['world_session_quest_id', 'beat', 'type', 'payload', 'by_creator'])]
class QuestEvent extends Model
{
    /** @use HasFactory<QuestEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => QuestEventType::class, 'payload' => 'array', 'by_creator' => 'boolean'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorldSessionQuest::class, 'world_session_quest_id');
    }

    /**
     * The log is the evidence an ending is judged on, so a written entry is
     * never changed; it goes only when its run does, through the database's
     * cascade.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Quest events are append-only.'));
        static::deleting(fn () => throw new LogicException('Quest events are append-only.'));
    }
}
