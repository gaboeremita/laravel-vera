<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assistant_user_id', 'discord_server_id', 'prompt'])]
class AssistantDiscordServer extends Model
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

    public function server(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class, 'discord_server_id');
    }
}
