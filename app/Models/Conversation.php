<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Always between two parties: the owner, who started it, and the counterpart
 * they talk to. Either can be a user or an assistant.
 */
#[Fillable(['owner_type', 'owner_id', 'counterpart_type', 'counterpart_id', 'status', 'resumed_at', 'world_session_id', 'discord_channel_id', 'title', 'long_term_memory', 'memory_checkpoint_message_id', 'memory_summarizing_at', 'auto_summarize_enabled'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * A conversation between residents with no line for this long counts as
     * stopped, so a closed tab never keeps anyone busy.
     */
    public const BUSY_SECONDS = 120;

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'resumed_at' => 'datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function counterpart(): MorphTo
    {
        return $this->morphTo();
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function observers(): HasMany
    {
        return $this->hasMany(ConversationObserver::class);
    }

    /**
     * The user–assistant pair a user's chat with an assistant belongs to, or
     * null for a conversation between two assistants.
     */
    public function assistantUser(): ?AssistantUser
    {
        [$user, $assistant] = match (true) {
            $this->owner_type === (new User)->getMorphClass() && $this->counterpart_type === (new Assistant)->getMorphClass() => [$this->owner_id, $this->counterpart_id],
            $this->owner_type === (new Assistant)->getMorphClass() && $this->counterpart_type === (new User)->getMorphClass() => [$this->counterpart_id, $this->owner_id],
            default => [null, null],
        };

        return $user === null ? null : AssistantUser::where('user_id', $user)->where('assistant_id', $assistant)->first();
    }

    public function involves(Model $party): bool
    {
        return ($this->owner_type === $party->getMorphClass() && $this->owner_id === $party->getKey())
            || ($this->counterpart_type === $party->getMorphClass() && $this->counterpart_id === $party->getKey());
    }

    /**
     * A user sees their own chats and every conversation between assistants
     * they have.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->involves($user)) {
            return true;
        }

        $assistantIds = $user->assistants()->pluck('assistants.id');
        $assistant = (new Assistant)->getMorphClass();

        return ($this->owner_type === $assistant && $assistantIds->contains($this->owner_id))
            || ($this->counterpart_type === $assistant && $assistantIds->contains($this->counterpart_id));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $assistantIds = $user->assistants()->pluck('assistants.id');
        $assistant = (new Assistant)->getMorphClass();
        $userType = $user->getMorphClass();

        $query->where(function (Builder $query) use ($user, $userType, $assistant, $assistantIds): void {
            $query->where(fn (Builder $query) => $query->where('owner_type', $userType)->where('owner_id', $user->id))
                ->orWhere(fn (Builder $query) => $query->where('counterpart_type', $userType)->where('counterpart_id', $user->id))
                ->orWhere(fn (Builder $query) => $query->where('owner_type', $assistant)->whereIn('owner_id', $assistantIds))
                ->orWhere(fn (Builder $query) => $query->where('counterpart_type', $assistant)->whereIn('counterpart_id', $assistantIds));
        });
    }

    /**
     * Conversations between residents that are going on right now.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLiveInSession(Builder $query, int $sessionId): void
    {
        $query->where('world_session_id', $sessionId)
            ->where('status', ConversationStatus::Active)
            ->where('owner_type', (new Assistant)->getMorphClass())
            ->where('updated_at', '>=', now()->subSeconds(self::BUSY_SECONDS));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInvolving(Builder $query, Model $party): void
    {
        $query->where(fn (Builder $query) => $query->whereMorphedTo('owner', $party)->orWhereMorphedTo('counterpart', $party));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeBetween(Builder $query, Model $first, Model $second): void
    {
        $query->where(function (Builder $query) use ($first, $second): void {
            $query->where(fn (Builder $query) => $query->whereMorphedTo('owner', $first)->whereMorphedTo('counterpart', $second))
                ->orWhere(fn (Builder $query) => $query->whereMorphedTo('owner', $second)->whereMorphedTo('counterpart', $first));
        });
    }
}
