<?php

namespace App\Models;

use Database\Factories\ActivityTermsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['region_id', 'object_id', 'activity_id', 'required_item_id', 'consumes_required', 'cost', 'gives_credits', 'gives_items', 'requirement', 'outcome', 'vendor_resident_id'])]
class ActivityTerms extends Model
{
    /** @use HasFactory<ActivityTermsFactory> */
    use HasFactory;

    protected $table = 'activity_terms';

    protected function casts(): array
    {
        return ['consumes_required' => 'boolean', 'cost' => 'integer', 'gives_credits' => 'integer', 'gives_items' => 'array'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function requiredItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'required_item_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(WorldResident::class, 'vendor_resident_id');
    }

    public function hasPlainLanguageTerms(): bool
    {
        return filled($this->requirement) || filled($this->outcome);
    }
}
