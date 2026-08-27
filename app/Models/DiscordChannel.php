<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['discord_server_id', 'discord_channel_id', 'name'])]
class DiscordChannel extends Model
{
    public function server(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class, 'discord_server_id');
    }

    public function assistantDiscordChannels(): HasMany
    {
        return $this->hasMany(AssistantDiscordChannel::class);
    }
}
