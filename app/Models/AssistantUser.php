<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['memory_prompt'])]
class AssistantUser extends Pivot
{

	public $incrementing = false;

	protected function casts(): array
	{
		return [
			'memory_prompt' => 'array',
		];
	}

	public function assistant(): BelongsTo
	{
		return $this->belongsTo(Assistant::class);
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	public function conversations(): HasMany
	{
		return $this->hasMany(Conversation::class, 'assistant_user_id');
	}
}
