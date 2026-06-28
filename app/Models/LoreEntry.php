<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class LoreEntry extends Model
{
	protected $fillable = [
		'title',
		'content',
		'keywords',
		'embedding',
		'lorebook_id'
	];

	protected $casts = [
		'keywords' => 'array',
		'embedding' => 'array',
	];

	public function lorebook(): BelongsTo
	{
		return $this->belongsTo(Lorebook::class);
	}

	public function tags(): MorphToMany
	{
		return $this->morphToMany(Tag::class, 'taggable');
	}
}
