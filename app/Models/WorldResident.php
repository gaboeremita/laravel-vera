<?php

namespace App\Models;

use App\Enums\WorldResidentBehavior;
use Database\Factories\WorldResidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assistant_id', 'position', 'rotation', 'behavior', 'behavior_settings', 'opening_message', 'custom_prompt', 'zone_access'])]
class WorldResident extends Model
{
    /** @use HasFactory<WorldResidentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'array', 'rotation' => 'array', 'behavior' => WorldResidentBehavior::class, 'behavior_settings' => 'array', 'zone_access' => 'array'];
    }

    /**
     * The groups she belongs to, which open private zones tagged for them.
     *
     * @return array<int, string>
     */
    public function accessTags(): array
    {
        return array_values($this->zone_access['tags'] ?? []);
    }

    /**
     * The private zones she may enter on her own, by id.
     *
     * @return array<int, string>
     */
    public function accessZoneIds(): array
    {
        return array_values($this->zone_access['zones'] ?? []);
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
