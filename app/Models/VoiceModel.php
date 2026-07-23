<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider_id', 'name', 'endpoint', 'voices', 'config', 'prompt'])]
class VoiceModel extends Model
{
	protected function casts(): array
	{
		return [
			'voices' => 'array',
			'config' => 'array',
			'prompt' => 'array',
		];
	}

	public function provider(): BelongsTo
	{
		return $this->belongsTo(VoiceProvider::class, 'provider_id');
	}
}
