<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['discord_guild_id', 'name'])]
class DiscordServer extends Model
{
    public function channels(): HasMany
    {
        return $this->hasMany(DiscordChannel::class);
    }

    public function assistantDiscordServers(): HasMany
    {
        return $this->hasMany(AssistantDiscordServer::class);
    }
}
