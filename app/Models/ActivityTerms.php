<?php

namespace App\Models;

use Database\Factories\ActivityTermsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What happens when the player uses one activity of an object: an ordered
 * list of responses, each a condition and the effects that run when it is the
 * first one met.
 */
#[Fillable(['region_id', 'object_id', 'activity_id', 'responses', 'vendor_resident_id'])]
class ActivityTerms extends Model
{
    /** @use HasFactory<ActivityTermsFactory> */
    use HasFactory;

    protected $table = 'activity_terms';

    protected function casts(): array
    {
        return ['responses' => 'array', 'vendor_resident_id' => 'integer'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(WorldResident::class, 'vendor_resident_id');
    }

    /**
     * @return array<int, array{condition: ?array, effects: array<int, array<string, mixed>>}>
     */
    public function responseList(): array
    {
        return $this->responses ?? [];
    }
}
