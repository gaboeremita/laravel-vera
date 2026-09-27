<?php

namespace App\Models;

use Database\Factories\PassageLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['region_id', 'passage_id', 'target_region_id', 'target_passage_id'])]
class PassageLink extends Model
{
    /** @use HasFactory<PassageLinkFactory> */
    use HasFactory;

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function targetRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'target_region_id');
    }
}
