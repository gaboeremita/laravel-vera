<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ArchiveEntry extends Model
{
    use HasFactory;

    protected $table = 'archive_entries';

    protected $fillable = [
        'title',
        'content',
        'keywords',
        'embedding',
        'archive_id',
    ];

    protected $casts = [
        'keywords' => 'array',
        'embedding' => 'array',
    ];

    public function archive(): BelongsTo
    {
        return $this->belongsTo(Archive::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Entries that reach a prompt through search or recall: every entry except
     * the state entries of items, which only their holders receive.
     *
     * @param  Builder<self>  $query
     */
    public function scopeRetrievable(Builder $query): void
    {
        $query->whereNotIn('id', Item::whereNotNull('state_entry_id')->select('state_entry_id'));
    }
}
