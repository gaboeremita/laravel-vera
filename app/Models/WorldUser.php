<?php

namespace App\Models;

use Database\Factories\WorldUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WorldUser extends Pivot
{
    /** @use HasFactory<WorldUserFactory> */
    use HasFactory;

    public $incrementing = true;

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WorldSession::class, 'world_user_id');
    }
}
