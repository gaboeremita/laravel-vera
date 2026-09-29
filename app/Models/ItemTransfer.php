<?php

namespace App\Models;

use Database\Factories\ItemTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item's movement between inventories; a null side is outside every
 * inventory, as in TransferInventory.
 */
#[Fillable(['world_session_id', 'from_inventory_id', 'to_inventory_id', 'item_id', 'quantity', 'reason', 'by_creator'])]
class ItemTransfer extends Model
{
    /** @use HasFactory<ItemTransferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'by_creator' => 'boolean'];
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
