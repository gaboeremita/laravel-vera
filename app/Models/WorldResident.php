<?php

namespace App\Models;

use App\Enums\WorldResidentBehavior;
use Database\Factories\WorldResidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assistant_id', 'position', 'rotation', 'behavior', 'behavior_settings'])]
class WorldResident extends Model
{
    /** @use HasFactory<WorldResidentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'array', 'rotation' => 'array', 'behavior' => WorldResidentBehavior::class, 'behavior_settings' => 'array'];
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }
}
