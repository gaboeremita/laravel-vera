<?php

namespace App\Http\Resources;

use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A conversation with both parties and who said each line, for reading it
 * back without taking part.
 *
 * @mixin Conversation
 */
class ConversationTranscriptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['owner', 'counterpart', 'worldSession.worldUser.world']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'owner' => $this->party($this->owner),
            'counterpart' => $this->party($this->counterpart),
            'world' => $this->worldSession?->worldUser?->world?->only(['id', 'name']),
            'worldSessionId' => $this->world_session_id,
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'messages' => $this->when(
                $this->relationLoaded('messages'),
                fn () => $this->messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'speaker' => $this->party($this->speakerOf($message)),
                    'content' => $message->content,
                    'createdAt' => $message->created_at?->toIso8601String(),
                ])->values(),
            ),
        ];
    }

    private function speakerOf(Message $message): ?Model
    {
        if ($message->speaker_type !== null) {
            return collect([$this->owner, $this->counterpart])
                ->first(fn (?Model $party) => $party?->getMorphClass() === $message->speaker_type && $party->getKey() === $message->speaker_id);
        }

        $assistantSide = $this->counterpart instanceof Assistant ? $this->counterpart : $this->owner;
        $userSide = $assistantSide === $this->owner ? $this->counterpart : $this->owner;

        return $message->role === 'assistant' ? $assistantSide : $userSide;
    }

    /**
     * @return ?array{type: string, id: int, name: string}
     */
    private function party(?Model $party): ?array
    {
        if ($party === null) {
            return null;
        }

        return [
            'type' => $party instanceof Assistant ? 'assistant' : 'user',
            'id' => $party->getKey(),
            'name' => $party->name,
        ];
    }
}
