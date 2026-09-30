<?php

namespace App\Actions;

use App\Actions\Activities\ActivityConditions;
use App\Actions\Activities\ActivityEffects;
use App\Actions\Activities\ActivityUse;
use App\Actions\Quests\QuestSessionState;
use App\Models\ActivityTerms;
use App\Models\Inventory;
use App\Models\Region;
use App\Models\WorldSession;
use Illuminate\Support\Facades\DB;

/**
 * The player uses an object's activity. The first response whose conditions
 * the session meets and whose effects can apply is the candidate; one
 * narrator call judges its narrator conditions, if it has any, and narrates
 * what happens, and the effects of the response that happened then run. An
 * activity without responses simply happens, with no call.
 */
class UseActivity
{
    public function __construct(
        private readonly ResolveInventory $resolveInventory,
        private readonly ActivityConditions $conditions,
        private readonly ActivityEffects $effects,
        private readonly Narrate $narrate,
    ) {}

    public function terms(Region $region, string $objectId, string $activityId): ?ActivityTerms
    {
        return $region->activityTerms()->where('object_id', $objectId)->where('activity_id', $activityId)->first();
    }

    /**
     * @return array{allowed: bool, narration: ?string, action: ?string, use: ?ActivityUse}
     */
    public function handle(WorldSession $session, Region $region, string $objectId, string $activityId, Inventory $player, string $playerName, ?string $attempt): array
    {
        $responses = $this->terms($region, $objectId, $activityId)?->responseList() ?? [];
        if ($responses === []) {
            return ['allowed' => true, 'narration' => null, 'action' => null, 'use' => null];
        }

        $use = new ActivityUse($session, $region, $objectId, $activityId, $player, $this->resolveInventory->forObject($session, $region, $objectId), $playerName, $attempt);
        $state = QuestSessionState::for($session);
        $met = collect($responses)->filter(fn (array $response) => $this->conditions->holds($response['condition'] ?? null, $use, $state))->values();

        $candidate = $met->first();
        if ($candidate === null) {
            $this->tellNarrator($use, ['Requirement' => 'it fails, because '.($this->conditions->unmetReason($responses[0]['condition'] ?? null, $use, $state) ?? 'nothing the player can do here works right now.')]);

            return $this->outcome(null, $use);
        }

        $missing = $this->missing($candidate, $use);
        if ($missing !== null) {
            $this->tellNarrator($use, ['Requirement' => "it fails, because {$missing}"]);

            return $this->outcome(null, $use);
        }

        $judged = $this->conditions->narratorLeaves($candidate['condition'] ?? null);
        if ($judged === []) {
            $this->tellNarrator($use, ['Requirement' => 'none; it succeeds', 'Outcome when it succeeds' => $this->describe($candidate, $use)]);

            return $this->outcome($candidate, $use);
        }

        $fallback = $met->slice(1)->first(fn (array $response) => $this->conditions->narratorLeaves($response['condition'] ?? null) === [] && $this->missing($response, $use) === null);
        $succeeded = $this->tellNarrator($use, [
            'Requirement' => collect($judged)->pluck('requirement')->filter()->implode(' ') ?: 'none; it succeeds',
            'Outcome when it succeeds' => $this->describe($candidate, $use),
            'Outcome when it fails' => $fallback !== null ? $this->describe($fallback, $use) : 'nothing happens',
        ]);

        return $this->outcome($succeeded ? $candidate : $fallback, $use);
    }

    /**
     * Why the response's effects cannot apply, for the narrator; null when they all can.
     *
     * @param  array{condition: ?array, effects: array<int, array<string, mixed>>}  $response
     */
    private function missing(array $response, ActivityUse $use): ?string
    {
        foreach ($this->effects->resolve($response['effects'] ?? []) as [$effect, $config]) {
            $missing = $effect->missing($config, $use);
            if ($missing !== null) {
                return $missing;
            }
        }

        return null;
    }

    /**
     * What happens when the response runs, for the narrator: the creator's
     * outcome for its narrator conditions, then what each effect does.
     *
     * @param  array{condition: ?array, effects: array<int, array<string, mixed>>}  $response
     */
    private function describe(array $response, ActivityUse $use): string
    {
        $parts = collect($this->conditions->narratorLeaves($response['condition'] ?? null))->pluck('outcome')
            ->merge(collect($this->effects->resolve($response['effects'] ?? []))->map(fn (array $pair) => $pair[0]->describe($pair[1], $use)))
            ->filter(fn (?string $part) => filled($part));

        return $parts->isEmpty() ? 'the activity simply happens' : $parts->implode(' ');
    }

    /**
     * The one narrator call of the use: it judges the requirement and writes
     * what the player reads.
     *
     * @param  array<string, string>  $outcomes  the requirement and what happens either way
     * @return bool whether the narrator judged the attempt a success
     */
    private function tellNarrator(ActivityUse $use, array $outcomes): bool
    {
        $verdict = $this->narrate->handle($use->session->worldUser->world, $use->region, array_filter([
            'Who' => $use->playerName,
            'Doing' => "{$use->activityName()} at the {$use->objectName()} ({$use->objectDescription()})",
            ...$outcomes,
            "{$use->playerName} carries" => $this->narrate->holdings($use->player),
            'What they do or say' => $use->attempt ?: 'nothing in particular',
        ]));
        $use->narration = $verdict['narration'];
        $use->action = $verdict['action'];

        return $verdict['succeeded'];
    }

    /**
     * Runs the effects of the response that happened; with none, the activity was refused.
     *
     * @param  ?array{condition: ?array, effects: array<int, array<string, mixed>>}  $response
     * @return array{allowed: bool, narration: ?string, action: ?string, use: ActivityUse}
     */
    private function outcome(?array $response, ActivityUse $use): array
    {
        if ($response !== null) {
            DB::transaction(function () use ($response, $use): void {
                foreach ($this->effects->resolve($response['effects'] ?? []) as [$effect, $config]) {
                    $effect->apply($config, $use);
                }
            });
        }

        return ['allowed' => $response !== null, 'narration' => $use->narration, 'action' => $use->action, 'use' => $use];
    }
}
