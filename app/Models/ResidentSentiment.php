<?php

namespace App\Models;

use App\Events\Quests\ResidentSentimentsChanged;
use Database\Factories\ResidentSentimentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a resident feels about the player in one session: a score from -10 to
 * 10 for each of the world's sentiments, starting at 0.
 */
#[Fillable(['world_session_id', 'world_resident_id', 'values'])]
class ResidentSentiment extends Model
{
    /** @use HasFactory<ResidentSentimentFactory> */
    use HasFactory;

    public const LIMIT = 10;

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    public static function of(WorldSession $session, WorldResident $resident): self
    {
        return self::firstOrCreate(['world_session_id' => $session->id, 'world_resident_id' => $resident->id]);
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->worldResident->world->sentimentNames();
    }

    /**
     * Adds each change to its sentiment, keeping every sentiment within the
     * limit; names the world doesn't have are ignored.
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

        $residentName = $this->worldResident->assistant->name;
        ResidentSentimentsChanged::dispatch($this->world_session_id, collect($changed)
            ->map(fn (string $name) => "{$residentName}'s {$name} is now ".number_format($values[$name], 1))
            ->implode('; '));
    }

    /**
     * Every sentiment of the world with its score; one never moved is 0.
     *
     * @return array<string, float>
     */
    public function scores(): array
    {
        $stored = $this->values ?? [];

        return collect($this->names())->mapWithKeys(fn (string $name) => [$name => (float) ($stored[$name] ?? 0)])->all();
    }

    public function worldSession(): BelongsTo
    {
        return $this->belongsTo(WorldSession::class);
    }

    public function worldResident(): BelongsTo
    {
        return $this->belongsTo(WorldResident::class);
    }
}
