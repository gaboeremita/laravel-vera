<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'data', 'assistant_id'])]
class Settings extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

	public function assistant(): BelongsTo
	{
		return $this->belongsTo(Assistant::class);
	}

	public static function ttsVoiceCacheKey(int $userId, int $assistantId): string
	{
		return "tts_voice:{$userId}:{$assistantId}";
	}
}
