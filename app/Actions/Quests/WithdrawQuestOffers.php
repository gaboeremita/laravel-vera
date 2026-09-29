<?php

namespace App\Actions\Quests;

use App\Enums\QuestEventType;
use App\Enums\QuestOfferStatus;
use App\Models\QuestOffer;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Withdraws offers the player never answered; their quests stay available.
 */
class WithdrawQuestOffers
{
    public function __construct(private readonly RecordQuestEvent $recordQuestEvent) {}

    /**
     * @param  Builder<QuestOffer>|HasMany<QuestOffer, WorldSession>  $offers
     */
    public function handle(Builder|HasMany $offers): void
    {
        $offers->where('status', QuestOfferStatus::Pending)->with('run')->get()->each(function (QuestOffer $offer): void {
            $offer->update(['status' => QuestOfferStatus::Withdrawn, 'answered_at' => now()]);
            $this->recordQuestEvent->handle($offer->run, QuestEventType::OfferWithdrawn);
        });
    }
}
