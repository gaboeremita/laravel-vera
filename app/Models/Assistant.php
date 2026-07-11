<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'prompt', 'opening_message'])]
class Assistant extends Model
{
	protected function casts(): array
	{
		return [
			'prompt' => 'array',
		];
	}

	public function users(): BelongsToMany
	{
		return $this->belongsToMany(User::class)
			->using(AssistantUser::class)
			->withTimestamps();
	}

	public function emotions(): HasMany
	{
		return $this->hasMany(Emotion::class);
	}
}
