<?php

namespace App\Models;

use App\Enums\AssistantKind;
use App\Enums\Posture;
use App\Enums\WorldResidentBehavior;
use Database\Factories\WorldResidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assistant_id', 'position', 'rotation', 'posture', 'behavior', 'behavior_settings', 'opening_message', 'custom_prompt', 'zone_access'])]
class WorldResident extends Model
{
    /** @use HasFactory<WorldResidentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'array', 'rotation' => 'array', 'posture' => Posture::class, 'behavior' => WorldResidentBehavior::class, 'behavior_settings' => 'array', 'zone_access' => 'array'];
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

    /**
     * The zones she keeps to, by id, zones inside them included; empty when
     * she may go anywhere.
     *
     * @return array<int, string>
     */
    public function areaZoneIds(): array
    {
        return array_values($this->behavior_settings['area'] ?? []);
    }

    /**
     * An NPC that stays put or walks its route keeps to it when someone talks
     * to her, so she is given no tools that move her.
     */
    public function staysAtPost(): bool
    {
        return $this->assistant?->kind === AssistantKind::WorldNpc
            && in_array($this->behavior, [WorldResidentBehavior::Stationary, WorldResidentBehavior::Route], true);
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
