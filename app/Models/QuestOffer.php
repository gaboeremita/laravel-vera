<?php

namespace App\Models;

use App\Enums\QuestOfferStatus;
use Database\Factories\QuestOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['world_session_id', 'world_session_quest_id', 'conversation_id', 'world_resident_id', 'status', 'answered_at'])]
class QuestOffer extends Model
{
    /** @use HasFactory<QuestOfferFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['status' => QuestOfferStatus::class, 'answered_at' => 'datetime'];
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorldSessionQuest::class, 'world_session_quest_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(WorldResident::class, 'world_resident_id');
    }

    /**
     * The offer as the player sees it.
     *
     * @return array{id: int, questTitle: string, description: string, giver: string, beats: array<int, array{text: string}>}
     */
    public function toPayload(): array
    {
        $quest = $this->run->quest;

        return [
            'id' => $this->id,
            'questTitle' => $quest->title,
            'description' => $quest->description(),
            'giver' => $this->giver->assistant->name,
            'beats' => collect($quest->beats())
                ->reject(fn (array $beat) => ($beat['hidden'] ?? false) || ($beat['requires'] ?? []) !== [])
                ->map(fn (array $beat) => ['text' => $beat['text']])
                ->values()
                ->all(),
        ];
    }
}
