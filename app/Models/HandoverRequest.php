<?php

namespace App\Models;

use App\Enums\HandoverRequestStatus;
use Database\Factories\HandoverRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['world_session_id', 'conversation_id', 'inventory_id', 'credits', 'items', 'reason', 'status', 'answered_at'])]
class HandoverRequest extends Model
{
    /** @use HasFactory<HandoverRequestFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['credits' => 'integer', 'items' => 'array', 'status' => HandoverRequestStatus::class, 'answered_at' => 'datetime'];
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The request as the player sees it.
     *
     * @return array{id: int, credits: int, items: array<int, array{itemId: int, name: string, quantity: int, cardImageUrl: ?string}>, reason: string, affordable: bool, askedBy: string}
     */
    public function toPayload(Inventory $player): array
    {
        $items = Item::with('cardImage')->whereKey(collect($this->items)->pluck('itemId'))->get()->keyBy('id');
        $held = $player->items()->pluck('quantity', 'item_id');
        $affordable = ($player->credits === null || $player->credits >= $this->credits)
            && collect($this->items)->every(fn (array $entry) => $held->has($entry['itemId']) && ($held[$entry['itemId']] === null || $held[$entry['itemId']] >= $entry['quantity']));

        return [
            'id' => $this->id,
            'credits' => $this->credits,
            'items' => collect($this->items)->map(fn (array $entry) => [
                'itemId' => $entry['itemId'],
                'name' => $items[$entry['itemId']]?->name ?? 'Unknown item',
                'quantity' => $entry['quantity'],
                'cardImageUrl' => $items[$entry['itemId']]?->cardImage?->url,
            ])->values()->all(),
            'reason' => $this->reason,
            'affordable' => $affordable,
            'askedBy' => $this->inventory->displayName(),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function itemQuantities(): array
    {
        return collect($this->items)->mapWithKeys(fn (array $entry) => [(int) $entry['itemId'] => (int) $entry['quantity']])->all();
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
}
