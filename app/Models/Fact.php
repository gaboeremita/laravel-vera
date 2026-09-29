<?php

namespace App\Models;

use Database\Factories\FactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['world_resident_id', 'topic', 'content', 'disclosure'])]
class Fact extends Model
{
    /** @use HasFactory<FactFactory> */
    use HasFactory;

    public function holder(): BelongsTo
    {
        return $this->belongsTo(WorldResident::class, 'world_resident_id');
    }

    /**
     * The residents who can act on the fact once the player knows it.
     */
    public function relays(): BelongsToMany
    {
        return $this->belongsToMany(WorldResident::class, 'fact_relays');
    }

    public function knownFacts(): HasMany
    {
        return $this->hasMany(KnownFact::class);
    }

    /**
     * How the fact reads in a list of every fact of the world, e.g. for the creator's tools.
     */
    public function label(): string
    {
        return "{$this->holder->assistant->name}: {$this->topic}";
    }
}
