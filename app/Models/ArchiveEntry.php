<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ArchiveEntry extends Model
{
	protected $table = 'archive_entries';

	protected $fillable = [
		'title',
		'content',
		'keywords',
		'embedding',
		'archive_id'
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
}
