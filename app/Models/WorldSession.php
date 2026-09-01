<?php

namespace App\Models;

use Database\Factories\WorldSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'position'])]
class WorldSession extends Model
{
    /** @use HasFactory<WorldSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'array'];
    }

    public function worldUser(): BelongsTo
    {
        return $this->belongsTo(WorldUser::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
