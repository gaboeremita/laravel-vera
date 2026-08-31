<?php

namespace App\Actions;

use App\Models\Assistant;
use App\Models\World;
use Illuminate\Auth\Access\AuthorizationException;

class AppendWorldConversationContext
{
    public function handle(Assistant $assistant, ?World $world): array
    {
        if ($world === null) {
            return $assistant->prompt;
        }

        if (! $world->residents()->where('assistant_id', $assistant->id)->exists()) {
            throw new AuthorizationException('The assistant is not a resident of this world.');
        }

        $prompt = $assistant->prompt;
        $prompt['world_context'] = [$world->contextPromptFor($assistant->kind)];

        return $prompt;
    }
}
