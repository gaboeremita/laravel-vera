<?php

namespace App\Actions;

use App\Models\Assistant;
use App\Models\World;
use Illuminate\Auth\Access\AuthorizationException;

class AppendWorldConversationContext
{
    public function __construct(
        private readonly ResolveWorldState $resolveWorldState = new ResolveWorldState,
        private readonly BuildResidentWorldPrompt $buildResidentWorldPrompt = new BuildResidentWorldPrompt,
    ) {}

    /**
     * @param  ?array{user?: array{x: float, y: float, z: float}, residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     */
    public function handle(Assistant $assistant, ?World $world, ?array $positions = null): array
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

        $residentPosition = $positions['residents'][$resident->id] ?? null;
        if ($residentPosition !== null && ! empty($world->layout['zones'])) {
            $state = $this->resolveWorldState->handle($world, $positions);
            $prompt['world_state'] = $this->buildResidentWorldPrompt->worldState($world, $state['residents'][$resident->id], $state['user']);
        }

        return $prompt;
    }
}
