<?php

namespace App\Models;

use App\Events\Quests\ResidentSentimentsChanged;
use Database\Factories\SentimentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How the assistant feels about the user in one conversation: a score from
 * -10 to 10 for each sentiment defined for it, starting at 0. A conversation
 * in a world session uses the world's sentiment definitions; any other uses
 * the assistant's.
 */
#[Fillable(['conversation_id', 'values'])]
class Sentiment extends Model
{
    /** @use HasFactory<SentimentFactory> */
    use HasFactory;

    public const LIMIT = 10;

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    public static function of(Conversation $conversation): self
    {
        return self::firstOrCreate(['conversation_id' => $conversation->id]);
    }

    public static function inSession(WorldSession $session, WorldResident $resident): self
    {
        return self::of(Conversation::withResident($session, $resident));
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    public function definitions(): array
    {
        $conversation = $this->conversation;
        $holder = $conversation->world_session_id !== null
            ? $conversation->worldSession->worldUser->world
            : $conversation->assistantUser()?->assistant;

        return $holder?->sentiment_definitions ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_column($this->definitions(), 'name');
    }

    /**
     * Adds each change to its sentiment, keeping every sentiment within the
     * limit; names without a definition are ignored.
     *
     * @param  array<string, float|int>  $changes  by sentiment name
     */
    public function adjust(array $changes): void
    {
        $values = $this->scores();
        $changed = [];
        foreach ($values as $name => $value) {
            if (! isset($changes[$name])) {
                continue;
            }
            $next = (float) max(-self::LIMIT, min(self::LIMIT, round($value + $changes[$name], 1)));
            if ($next !== $value) {
                $values[$name] = $next;
                $changed[] = $name;
            }
        }

        if ($changed === []) {
            return;
        }

        $this->values = [...($this->values ?? []), ...$values];
        $this->save();

        $sessionId = $this->conversation->world_session_id;
        if ($sessionId === null) {
            return;
        }

        $residentName = $this->conversation->assistantUser()->assistant->name;
        ResidentSentimentsChanged::dispatch($sessionId, collect($changed)
            ->map(fn (string $name) => "{$residentName}'s {$name} is now ".number_format($values[$name], 1))
            ->implode('; '));
    }

    /**
     * Every defined sentiment with its score; one never moved is 0.
     *
     * @return array<string, float>
     */
    public function scores(): array
    {
        $stored = $this->values ?? [];

        return collect($this->names())->mapWithKeys(fn (string $name) => [$name => (float) ($stored[$name] ?? 0)])->all();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
