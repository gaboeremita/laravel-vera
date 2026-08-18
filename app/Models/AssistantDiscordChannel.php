<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assistant_user_id', 'discord_channel_id', 'trigger_mode', 'prompt'])]
class AssistantDiscordChannel extends Model
{
	protected function casts(): array
	{
		return [
			'prompt' => 'array',
		];
	}

	public function assistantUser(): BelongsTo
	{
		return $this->belongsTo(AssistantUser::class);
	}

	public function channel(): BelongsTo
	{
		return $this->belongsTo(DiscordChannel::class, 'discord_channel_id');
	}
}
