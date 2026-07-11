<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AssistantUser extends Pivot
{

	public $incrementing = false;

	public function assistant(): BelongsTo
	{
		return $this->belongsTo(Assistant::class);
	}

	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}
}
