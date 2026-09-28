<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['world_id', 'name', 'description', 'base_price', 'contents', 'use_requirement', 'consumed_on_use', 'releases_credits', 'releases_items'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['base_price' => 'integer', 'consumed_on_use' => 'boolean', 'releases_credits' => 'integer', 'releases_items' => 'array'];
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function cardImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('role', 'card');
    }
}
