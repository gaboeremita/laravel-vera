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

        $resident = $world->residents()->where('assistant_id', $assistant->id)->first();

        if ($resident === null) {
            throw new AuthorizationException('The assistant is not a resident of this world.');
        }

        $prompt = $assistant->prompt;
        $prompt['world_context'] = array_filter([$world->contextPromptFor($assistant->kind), $resident->custom_prompt]);

        return $prompt;
    }
}
