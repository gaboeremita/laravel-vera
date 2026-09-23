<?php

namespace App\Services\AgentLoop\Tools\World;

use RuntimeException;

class PlanTool extends WorldTool
{
    private const MAX_STEPS = 5;

    private const STEP_ACTIONS = ['go_to', 'use', 'zone', 'pose', 'do', 'stay'];

    public function name(): string
    {
        return 'plan';
    }

    public function description(): string
    {
        return 'Does something that takes several steps, in order, such as getting a drink: go_to the bar, use the back bar to mix a drink, then use a stool to drink it. Each step is go_to (target), use (target spot and activity), zone (activity of the place you are in by then), pose (a pose of yours), do (anything else, described in a few words, such as singing a song or making tea where you stand; your narration carries it) or stay. Steps run one after another; if one fails, the rest stop and you decide again.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'goal' => ['type' => 'string', 'description' => 'What the whole plan is for, in a few words.'],
                'steps' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => self::MAX_STEPS,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string', 'enum' => self::STEP_ACTIONS],
                            'target' => ['type' => 'string', 'description' => 'For go_to, a place or thing id; for use, a spot id.'],
                            'activity' => ['type' => 'string', 'description' => 'For use and zone, the activity id.'],
                            'pose' => ['type' => 'string', 'description' => 'For pose, one of your poses. For use, zone and do, optionally the pose of yours that fits the step, when an activity\'s own pose is named differently from yours. Your poses: '.($this->toolbox->poseNames === [] ? 'none' : implode(', ', $this->toolbox->poseNames)).'.'],
                            'description' => ['type' => 'string', 'description' => 'For do, what you do, in a few words.'],
                        ],
                        'required' => ['action'],
                    ],
                ],
            ],
            'required' => ['goal', 'steps'],
        ];
    }

    public function handle(array $arguments): array
    {
        $goal = trim((string) ($arguments['goal'] ?? ''));
        $steps = $arguments['steps'] ?? [];
        if ($goal === '' || ! is_array($steps) || $steps === []) {
            throw new RuntimeException('plan needs a goal and at least one step.');
        }
        if (count($steps) > self::MAX_STEPS) {
            throw new RuntimeException(sprintf('A plan has at most %d steps.', self::MAX_STEPS));
        }

        $location = $this->toolbox->residentZoneChain;
        $normalized = [];
        foreach (array_values($steps) as $index => $step) {
            try {
                [$normalized[], $location] = $this->checkStep(is_array($step) ? $step : [], $location);
            } catch (RuntimeException $e) {
                throw new RuntimeException(sprintf('Step %d: %s Fix it and call plan again.', $index + 1, $e->getMessage()));
            }
        }

        $this->toolbox->choose(['verb' => 'plan', 'target' => mb_substr($goal, 0, 255), 'activity' => null, 'steps' => $normalized]);

        return ['status' => 'started', 'note' => sprintf('You start on your plan to %s: %d steps.', $goal, count($normalized))];
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<int, array<string, mixed>>  $location  zones she will be in when this step starts
     * @return array{0: array{verb: string, target: ?string, activity: ?string, pose: ?string, description: ?string}, 1: array<int, array<string, mixed>>}
     */
    private function checkStep(array $step, array $location): array
    {
        $action = (string) ($step['action'] ?? '');
        $target = trim((string) ($step['target'] ?? ''));
        $activityId = trim((string) ($step['activity'] ?? ''));
        $pose = trim((string) ($step['pose'] ?? ''));
        $description = trim((string) ($step['description'] ?? ''));
        $normalized = ['verb' => $action, 'target' => null, 'activity' => null, 'pose' => null, 'description' => $description !== '' ? mb_substr($description, 0, 200) : null];

        switch ($action) {
            case 'go_to':
                $zone = $this->toolbox->findZone($target);
                $object = $zone === null ? $this->toolbox->findObject($target) : null;
                if ($zone === null && $object === null) {
                    throw new RuntimeException(sprintf('There is no place or thing called "%s" here.', $target));
                }

                return [[...$normalized, 'target' => ($zone ?? $object)['id']], $this->toolbox->zoneChainOf($zone['id'] ?? $object['zoneId'])];
            case 'use':
                $spot = $this->toolbox->findSpot($target);
                if ($spot === null) {
                    throw new RuntimeException(sprintf('There is no spot called "%s" here.', $target));
                }
                $activity = collect($spot['activities'])->first(fn (array $candidate) => $this->toolbox->sameName($candidate['id'], $activityId) || $this->toolbox->sameName($candidate['name'], $activityId));
                if ($activity === null) {
                    throw new RuntimeException(sprintf('"%s" cannot be done at %s. It offers: %s.', $activityId, $spot['id'], collect($spot['activities'])->pluck('id')->implode(', ')));
                }
                if (in_array($spot['id'], $this->toolbox->occupiedSpots, true)) {
                    throw new RuntimeException(sprintf('%s is taken by someone else right now.', $spot['id']));
                }
                $object = $this->toolbox->findObject($spot['objectId']);

                return [[...$normalized, 'target' => $spot['id'], 'activity' => $activity['id'], 'pose' => $this->toolbox->poseForActivity($activity, $pose)], $this->toolbox->zoneChainOf($object['zoneId'] ?? null)];
            case 'zone':
                $zones = $location !== [] ? $location : $this->toolbox->zones();
                $activity = collect($zones)->flatMap(fn (array $zone) => $zone['activities'])
                    ->first(fn (array $candidate) => $this->toolbox->sameName($candidate['id'], $activityId) || $this->toolbox->sameName($candidate['name'], $activityId));
                if ($activity === null) {
                    throw new RuntimeException(sprintf('"%s" is not something you can do in the place you will be in by then.', $activityId));
                }

                return [[...$normalized, 'activity' => $activity['id'], 'pose' => $this->toolbox->poseForActivity($activity, $pose)], $location];
            case 'pose':
                $name = collect($this->toolbox->poseNames)->first(fn (string $candidate) => $this->toolbox->sameName($candidate, $pose));
                if ($name === null) {
                    throw new RuntimeException(sprintf('You have no pose called "%s".', $pose));
                }

                return [[...$normalized, 'target' => $name], $location];
            case 'do':
                if ($normalized['description'] === null) {
                    throw new RuntimeException('A do step needs a description of what you do.');
                }

                return [[...$normalized, 'pose' => $this->toolbox->poseForActivity([], $pose)], $location];
            case 'stay':
                return [$normalized, $location];
            default:
                throw new RuntimeException(sprintf('"%s" is not a step you can take; use one of %s.', $action, implode(', ', self::STEP_ACTIONS)));
        }
    }
}
