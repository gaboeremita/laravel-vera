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
        private readonly ApplyResidentZoneAccess $applyResidentZoneAccess = new ApplyResidentZoneAccess,
    ) {}

    /**
     * @param  ?array{user?: array{x: float, y: float, z: float}, residents?: array<int|string, array{x: float, y: float, z: float}>}  $positions
     * @param  ?array{posture: string, object: ?array, activity: ?array}  $userActivity
     * @param  array<int, array{spotId: string, holders: array<int, string>}>  $stackedSpots
     * @param  ?string  $userTalkingWith  the name of whoever the user is busy talking with
     * @param  ?array{posture: string, object: ?array, activity: ?array}  $residentActivity
     * @param  array<int, ?int>  $busyResidents  for each resident who is busy, the resident they are talking with or on their way to
     */
    public function handle(Assistant $assistant, ?World $world, ?array $positions = null, ?WorldSession $session = null, ?array $userActivity = null, array $stackedSpots = [], ?string $userTalkingWith = null, ?array $residentActivity = null, array $busyResidents = []): array
    {
        if ($world === null) {
            return $assistant->prompt;
        }

        $resident = $world->residents()->where('assistant_id', $assistant->id)->first();

        if ($resident === null) {
            throw new AuthorizationException('The assistant is not a resident of this world.');
        }

        $world = $this->applyResidentZoneAccess->handle($world, $resident);

        $prompt = $assistant->prompt;
        $prompt['world_context'] = array_filter([$world->contextPromptFor($assistant->kind), $resident->custom_prompt]);

        // The parts that stay the same from one call to the next come first, so
        // the model provider can reuse them as a cached prefix.
        $atPost = $resident->staysAtPost();
        if (! empty($world->layout['zones'])) {
            $prompt['world_awareness'] = $atPost
                ? $this->buildResidentWorldPrompt->postAwareness()
                : $this->buildResidentWorldPrompt->worldAwareness();
            if (! $atPost) {
                $prompt['world_places'] = ['title' => 'Places in this world', 'available places' => $this->buildResidentWorldPrompt->availablePlaces($world)];
            }
        }

        $residentPosition = $positions['residents'][$resident->id] ?? null;
        $residentZone = null;
        if ($residentPosition !== null && ! empty($world->layout['zones'])) {
            $state = $this->resolveWorldState->handle($world, $positions);
            $residentZone = $state['residents'][$resident->id]['zone'];
            $stacking = $this->resolveSpotStacking->handle($world, $resident, $stackedSpots);
            $userInSight = isset($positions['user']) && $this->resolveWorldState->sharesRoom($world->layout, $residentPosition, $positions['user']);
            $withYou = $this->buildResidentWorldPrompt->companions($world, $resident, $positions, $busyResidents);
            $prompt['world_state'] = $this->buildResidentWorldPrompt->worldState($world, $state['residents'][$resident->id], $state['user'], $userActivity, $stacking, $userTalkingWith, $residentActivity, $userInSight, $withYou, lean: $atPost);
        }

        if ($session !== null) {
            $currentActivity = $this->buildResidentWorldPrompt->currentActivity($session, $resident, $residentZone, $residentActivity);
            if ($currentActivity !== null) {
                $prompt['current_activity'] = $currentActivity;
            }

            $recentActivity = $this->buildResidentWorldPrompt->recentActivity($world, $session, $resident, $atPost ? BuildResidentWorldPrompt::POST_ACTIVITY_LIMIT : null);
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
