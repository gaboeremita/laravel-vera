<?php

use App\Models\Conversation;
use App\Models\User;
use App\Models\WorldSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    return Conversation::find($conversationId)?->isVisibleTo($user) ?? false;
});

Broadcast::channel('world-session.{sessionId}', function (User $user, int $sessionId) {
    return WorldSession::whereKey($sessionId)->whereHas('worldUser', fn ($query) => $query->where('user_id', $user->id))->exists();
});
