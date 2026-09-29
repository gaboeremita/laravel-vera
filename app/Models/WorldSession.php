<?php

namespace App\Models;

use Database\Factories\WorldSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'region_id', 'position', 'arrival_facing'])]
class WorldSession extends Model
{
    /** @use HasFactory<WorldSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'array', 'arrival_facing' => 'float'];
    }

    public function worldUser(): BelongsTo
    {
        return $this->belongsTo(WorldUser::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function residentStates(): HasMany
    {
        return $this->hasMany(WorldSessionResident::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function handoverRequests(): HasMany
    {
        return $this->hasMany(HandoverRequest::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
