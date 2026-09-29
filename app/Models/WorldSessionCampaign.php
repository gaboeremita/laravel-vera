<?php

namespace App\Models;

use App\Enums\EndingStatus;
use Database\Factories\WorldSessionCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A campaign's ending in a session, created once the campaign is over.
 */
#[Fillable(['world_session_id', 'campaign_id', 'ending', 'ending_status'])]
class WorldSessionCampaign extends Model
{
    /** @use HasFactory<WorldSessionCampaignFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['ending' => 'array', 'ending_status' => EndingStatus::class];
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
