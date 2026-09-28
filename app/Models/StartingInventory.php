<?php

namespace App\Models;

use App\Enums\InventoryHolder;
use Database\Factories\StartingInventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['world_id', 'holder', 'world_resident_id', 'region_id', 'object_id', 'credits'])]
class StartingInventory extends Model
{
    /** @use HasFactory<StartingInventoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['holder' => InventoryHolder::class, 'credits' => 'integer'];
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function worldResident(): BelongsTo
    {
        return $this->belongsTo(WorldResident::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StartingInventoryItem::class);
    }
}
