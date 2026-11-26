<?php

namespace App\Models;

use App\Enums\InventoryHolder;
use Database\Factories\WorldSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'region_id', 'position', 'arrival_facing', 'creator_mode_at', 'frozen_resident_ids'])]
class WorldSession extends Model
{
    /** @use HasFactory<WorldSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['position' => 'array', 'arrival_facing' => 'float', 'creator_mode_at' => 'datetime', 'frozen_resident_ids' => 'array'];
    }

    public function latestRunOf(Quest $quest): ?WorldSessionQuest
    {
        return $this->questRuns()->where('quest_id', $quest->id)->latest('run')->first()?->setRelation('quest', $quest);
    }

    public function isFrozen(WorldResident $resident): bool
    {
        return in_array($resident->id, $this->frozen_resident_ids ?? [], true);
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

    public function objectStates(): HasMany
    {
        return $this->hasMany(WorldSessionObject::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * The residents who hold an item with a state entry in this session.
     *
     * @return array<int, int>
     */
    public function residentIdsHoldingState(): array
    {
        return $this->inventories()
            ->where('holder', InventoryHolder::Resident)
            ->whereHas('items.item', fn ($query) => $query->whereNotNull('state_entry_id'))
            ->pluck('world_resident_id')
            ->all();
    }

    /**
     * The residents who hold an offered item with no state entry in this session.
     *
     * @return array<int, int>
     */
    public function residentIdsHoldingFake(): array
    {
        return $this->inventories()
            ->where('holder', InventoryHolder::Resident)
            ->whereHas('items.item', fn ($query) => $query->whereNotNull('offered_by_assistant_id')->whereNull('state_entry_id'))
            ->pluck('world_resident_id')
            ->all();
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function handoverRequests(): HasMany
    {
        return $this->hasMany(HandoverRequest::class);
    }

    public function knownFacts(): HasMany
    {
        return $this->hasMany(KnownFact::class);
    }

    public function factAcknowledgements(): HasMany
    {
        return $this->hasMany(FactAcknowledgement::class);
    }

    public function revealAttempts(): HasMany
    {
        return $this->hasMany(RevealAttempt::class);
    }

    public function questRuns(): HasMany
    {
        return $this->hasMany(WorldSessionQuest::class);
    }

    public function questOffers(): HasMany
    {
        return $this->hasMany(QuestOffer::class);
    }

    public function campaignEndings(): HasMany
    {
        return $this->hasMany(WorldSessionCampaign::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
