<?php

namespace App\Actions;

use App\Models\Assistant;
use App\Models\World;
use App\Models\WorldSession;
use Illuminate\Auth\Access\AuthorizationException;

class AppendWorldConversationContext
{
    public function __construct(
        private readonly ResolveWorldState $resolveWorldState = new ResolveWorldState,
        private readonly BuildResidentWorldPrompt $buildResidentWorldPrompt = new BuildResidentWorldPrompt,
        private readonly ResolveSpotStacking $resolveSpotStacking = new ResolveSpotStacking,
    ) {}

    /**
     * @param  ?array{user?: array{x: float, y: float, z: float}, residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     * @param  ?array{posture: string, object: ?array, activity: ?array}  $userActivity
     * @param  array<int, array{spotId: string, holders: array<int, string>}>  $stackedSpots
     * @param  ?string  $userTalkingWith  the name of whoever the user is busy talking with
     * @param  ?array{posture: string, object: ?array, activity: ?array}  $residentActivity
     */
    public function handle(Assistant $assistant, ?World $world, ?array $positions = null, ?WorldSession $session = null, ?array $userActivity = null, array $stackedSpots = [], ?string $userTalkingWith = null, ?array $residentActivity = null): array
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
        $residentZone = null;
        if ($residentPosition !== null && ! empty($world->layout['zones'])) {
            $state = $this->resolveWorldState->handle($world, $positions);
            $residentZone = $state['residents'][$resident->id]['zone'];
            $stacking = $this->resolveSpotStacking->handle($world, $resident, $stackedSpots);
            $prompt['world_state'] = $this->buildResidentWorldPrompt->worldState($world, $state['residents'][$resident->id], $state['user'], $userActivity, $stacking, $userTalkingWith, $residentActivity);
        }

        if (! empty($world->layout['zones'])) {
            $prompt['world_awareness'] = $this->buildResidentWorldPrompt->worldAwareness();
        }

        if ($session !== null) {
            $currentActivity = $this->buildResidentWorldPrompt->currentActivity($session, $resident, $residentZone, $residentActivity);
            if ($currentActivity !== null) {
                $prompt['current_activity'] = $currentActivity;
            }

            $recentActivity = $this->buildResidentWorldPrompt->recentActivity($world, $session, $resident);
            if ($recentActivity !== null) {
                $prompt['recent_activity'] = $recentActivity;
            }

            $otherConversations = $this->buildResidentWorldPrompt->conversationsWithOthers($assistant, $session);
            if ($otherConversations !== null) {
                $prompt['conversations_with_others'] = $otherConversations;
            }
        }

        return $prompt;
    }
}
