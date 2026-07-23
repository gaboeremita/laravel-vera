<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assistant_user_id', 'title', 'long_term_memory', 'memory_checkpoint_message_id', 'memory_summarizing_at', 'auto_summarize_enabled'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

	public function assistantUser(): BelongsTo
	{
		return $this->belongsTo(AssistantUser::class);
	}

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
