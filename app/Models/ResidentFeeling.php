<?php

namespace App\Models;

use App\Events\Quests\ResidentFeelingsChanged;
use Database\Factories\ResidentFeelingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a resident feels about the player in one session: romance, trust and
 * liking, each from -10 to 10, starting at 0.
 */
#[Fillable(['world_session_id', 'world_resident_id', 'romance', 'trust', 'liking'])]
class ResidentFeeling extends Model
{
    /** @use HasFactory<ResidentFeelingFactory> */
    use HasFactory;

    public const FEELINGS = ['romance', 'trust', 'liking'];

    public const LIMIT = 10;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'romance' => 0,
        'trust' => 0,
        'liking' => 0,
    ];

    protected function casts(): array
    {
        return ['romance' => 'float', 'trust' => 'float', 'liking' => 'float'];
    }

    public static function of(WorldSession $session, WorldResident $resident): self
    {
        return self::firstOrCreate(['world_session_id' => $session->id, 'world_resident_id' => $resident->id]);
    }

    /**
     * Adds each change to its feeling, keeping every feeling within the limit.
     *
     * @param  array<string, float|int>  $changes  by feeling name
     */
    public function adjust(array $changes): void
    {
        foreach (self::FEELINGS as $feeling) {
            if (isset($changes[$feeling])) {
                $this->{$feeling} = max(-self::LIMIT, min(self::LIMIT, round($this->{$feeling} + $changes[$feeling], 1)));
            }
        }
        $changed = array_keys($this->getDirty());
        $this->save();

        if ($changed !== []) {
            $name = $this->worldResident->assistant->name;
            ResidentFeelingsChanged::dispatch($this->world_session_id, collect($changed)
                ->map(fn (string $feeling) => "{$name}'s {$feeling} is now ".number_format($this->{$feeling}, 1))
                ->implode('; '));
        }
    }

    /**
     * @return array{romance: float, trust: float, liking: float}
     */
    public function values(): array
    {
        return ['romance' => $this->romance, 'trust' => $this->trust, 'liking' => $this->liking];
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
