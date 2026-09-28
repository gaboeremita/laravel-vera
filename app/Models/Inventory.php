<?php

namespace App\Models;

use App\Enums\InventoryHolder;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What one holder has in one session. A null credits balance or item quantity is unlimited.
 */
#[Fillable(['world_session_id', 'holder', 'world_resident_id', 'region_id', 'object_id', 'credits'])]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['holder' => InventoryHolder::class, 'credits' => 'integer'];
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
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
        return $this->hasMany(InventoryItem::class);
    }

    public function isVendor(): bool
    {
        return $this->holder === InventoryHolder::Resident && $this->items()->where('for_sale', true)->exists();
    }

    /**
     * @return array{credits: ?int, items: array<int, array{itemId: int, name: string, description: string, quantity: ?int, basePrice: ?int, cardImageUrl: ?string, canExamine: bool, canUse: bool}>}
     */
    public function summary(bool $forSaleOnly = false): array
    {
        $items = $this->items()->with('item.cardImage')->when($forSaleOnly, fn ($query) => $query->where('for_sale', true))->get();

        return [
            'credits' => $this->credits,
            'items' => $items->sortBy(fn (InventoryItem $held) => $held->item->name)->map(fn (InventoryItem $held) => [
                'itemId' => $held->item_id,
                'name' => $held->item->name,
                'description' => $held->item->description,
                'quantity' => $held->quantity,
                'basePrice' => $held->item->base_price,
                'cardImageUrl' => $held->item->cardImage?->url,
                'canExamine' => filled($held->item->contents),
                'canUse' => filled($held->item->use_requirement) || $held->item->releases_credits > 0 || ! empty($held->item->releases_items),
            ])->values()->all(),
        ];
    }

    /**
     * The signed difference between two summaries of the same inventory.
     *
     * @param  array{credits: ?int, items: array<int, array{itemId: int, name: string, quantity: ?int}>}  $before
     * @param  array{credits: ?int, items: array<int, array{itemId: int, name: string, quantity: ?int}>}  $after
     * @return array{credits: int, items: array<int, array{itemId: int, name: string, delta: int}>}
     */
    public static function changesBetween(array $before, array $after): array
    {
        $quantities = fn (array $summary) => collect($summary['items'])->keyBy('itemId');
        $was = $quantities($before);
        $now = $quantities($after);

        $items = $was->keys()->merge($now->keys())->unique()->map(fn (int $itemId) => [
            'itemId' => $itemId,
            'name' => ($now[$itemId] ?? $was[$itemId])['name'],
            'delta' => (int) ($now[$itemId]['quantity'] ?? 0) - (int) ($was[$itemId]['quantity'] ?? 0),
        ])->filter(fn (array $change) => $change['delta'] !== 0)->values()->all();

        return ['credits' => (int) $after['credits'] - (int) $before['credits'], 'items' => $items];
    }

    public function displayName(): string
    {
        return match ($this->holder) {
            InventoryHolder::Player => $this->worldSession->worldUser->user->name,
            InventoryHolder::Resident => $this->worldResident->assistant->name,
            InventoryHolder::Object => collect($this->region->layout['objects'] ?? [])->firstWhere('id', $this->object_id)['name'] ?? $this->object_id,
        };
    }
}
