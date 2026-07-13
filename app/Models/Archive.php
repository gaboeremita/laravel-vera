<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Archive extends Model
{
	protected $table = 'archives';

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
		return $this->hasMany(ArchiveEntry::class);
	}
}
