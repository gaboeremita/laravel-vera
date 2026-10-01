<?php

namespace App\Actions;

use App\Directors\PromptDirector;
use App\Enums\TurnSection;
use App\Models\Assistant;
use App\Models\Region;
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
    public function handle(PromptDirector $director, Assistant $assistant, ?Region $region, ?array $positions = null, ?WorldSession $session = null, ?array $userActivity = null, array $stackedSpots = [], ?string $userTalkingWith = null, ?array $residentActivity = null, array $busyResidents = []): void
    {
        if ($region === null) {
            return;
        }

        $resident = $region->world->residents()->where('assistant_id', $assistant->id)->first();

        if ($resident === null) {
            throw new AuthorizationException('The assistant is not a resident of this world.');
        }

        $region = $this->applyResidentZoneAccess->handle($region, $resident);

        $director->append('world_context', array_values(array_filter([
            $region->world->contextPromptFor($assistant->kind),
            $region->contextPromptFor($assistant->kind),
            "You are in {$region->name}.",
            $resident->custom_prompt,
        ])));

        $atPost = $resident->staysAtPost();
        if (! empty($region->layout['zones'])) {
            $director->append('world_awareness', $atPost
                ? $this->buildResidentWorldPrompt->postAwareness()
                : $this->buildResidentWorldPrompt->worldAwareness());
            if (! $atPost) {
                $director->append('world_places', ['title' => 'Places in this world', ...$this->buildResidentWorldPrompt->availablePlaces($region)]);
            }
        }

        $neighbours = $this->buildResidentWorldPrompt->neighbours($resident);
        if ($neighbours !== null) {
            $director->append('neighbours', $neighbours);
        }

        if (! empty($region->layout['zones'])) {
            $director->append('poses and movement', $this->buildResidentWorldPrompt->posesAndMovement($atPost));
        }

        $residentPosition = $positions['residents'][$resident->id] ?? null;
        $residentZone = null;
        if ($residentPosition !== null && ! empty($region->layout['zones'])) {
            $state = $this->resolveWorldState->handle($region, $positions);
            $residentZone = $state['residents'][$resident->id]['zone'];
            $stacking = $this->resolveSpotStacking->handle($region, $resident, $stackedSpots);
            $userInSight = isset($positions['user']) && $this->resolveWorldState->sharesRoom($region->layout, $residentPosition, $positions['user']);
            $withYou = $this->buildResidentWorldPrompt->companions($region, $resident, $positions, $busyResidents);
            $director->addToTurn(TurnSection::CurrentState, 'world_state', $this->buildResidentWorldPrompt->worldState($region, $state['residents'][$resident->id], $state['user'], $userActivity, $stacking, $userTalkingWith, $residentActivity, $userInSight, $withYou, lean: $atPost));
        }

        if ($session !== null) {
            $currentActivity = $this->buildResidentWorldPrompt->currentActivity($session, $resident, $residentZone, $residentActivity);
            if ($currentActivity !== null) {
                $director->addToTurn(TurnSection::CurrentState, 'current_activity', $currentActivity);
            }

            $recentActivity = $this->buildResidentWorldPrompt->recentActivity($region, $session, $resident, $atPost ? BuildResidentWorldPrompt::POST_ACTIVITY_LIMIT : null);
            if ($recentActivity !== null) {
                $director->addToTurn(TurnSection::RecentActivity, 'recent_activity', $recentActivity);
            }

            $otherConversations = $this->buildResidentWorldPrompt->conversationsWithOthers($assistant, $session);
            if ($otherConversations !== null) {
                $director->addToTurn(TurnSection::RecentActivity, 'conversations_with_others', $otherConversations);
            }
        }
    }
}
