<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lorebook extends Model
{
	protected $fillable = [
		'name',
		'description',
		'user_id'
	];

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function entries(): HasMany
	{
		return $this->hasMany(LoreEntry::class);
	}
}
