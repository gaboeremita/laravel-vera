<?php

namespace App\Models;

use Database\Factories\StartingInventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['starting_inventory_id', 'item_id', 'quantity', 'for_sale', 'takeable'])]
class StartingInventoryItem extends Model
{
    /** @use HasFactory<StartingInventoryItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'for_sale' => 'boolean', 'takeable' => 'boolean'];
    }

    public function startingInventory(): BelongsTo
    {
        return $this->belongsTo(StartingInventory::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
