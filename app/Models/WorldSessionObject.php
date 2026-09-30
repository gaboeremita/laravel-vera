<?php

namespace App\Models;

use Database\Factories\WorldSessionObjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What activity effects changed about one object of the region, for one session.
 */
#[Fillable(['world_session_id', 'region_id', 'object_id', 'state'])]
class WorldSessionObject extends Model
{
    /** @use HasFactory<WorldSessionObjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['state' => 'array'];
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function has(string $key): bool
    {
        return (bool) ($this->state[$key] ?? false);
    }
}
