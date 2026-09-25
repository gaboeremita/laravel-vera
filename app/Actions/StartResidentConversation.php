<?php

namespace App\Actions;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Support\Facades\Cache;

class StartResidentConversation
{
    private const LOCK_SECONDS = 10;

    /**
     * The one conversation two residents have in a session: started the first
     * time, resumed every time after, whoever speaks first, with the
     * starter's opening line.
     */
    /**
     * @param  ?array<string, mixed>  $expression  how she said it, as her decision recorded it
     */
    public function handle(WorldSession $session, WorldResident $starter, WorldResident $other, string $line, ?array $expression = null): Conversation
    {
        $starter->loadMissing('assistant');
        $other->loadMissing('assistant');
        $pair = collect([$starter->assistant->id, $other->assistant->id])->sort()->implode('-');

        // Two residents turning to each other at the same moment would both
        // miss the other's new conversation; one at a time, the second resumes it.
        return Cache::lock("resident-conversation:{$session->id}:{$pair}", self::LOCK_SECONDS)->block(self::LOCK_SECONDS, function () use ($session, $starter, $other, $line, $expression): Conversation {
            $conversation = Conversation::between($starter->assistant, $other->assistant)
                ->where('world_session_id', $session->id)
                ->first();

            if ($conversation === null) {
                $conversation = Conversation::create([
                    'owner_type' => $starter->assistant->getMorphClass(),
                    'owner_id' => $starter->assistant->id,
                    'counterpart_type' => $other->assistant->getMorphClass(),
                    'counterpart_id' => $other->assistant->id,
                    'world_session_id' => $session->id,
                    'title' => "{$starter->assistant->name} and {$other->assistant->name}",
                    'status' => ConversationStatus::Active,
                    'resumed_at' => now(),
                ]);
            } else {
                $conversation->update(['status' => ConversationStatus::Active, 'resumed_at' => now()]);
            }

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $line,
                'speaker_type' => $starter->assistant->getMorphClass(),
                'speaker_id' => $starter->assistant->id,
                'expression' => $expression,
            ]);

            return $conversation;
        });
    }
}
