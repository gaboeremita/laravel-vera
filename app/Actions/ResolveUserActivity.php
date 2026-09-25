<?php

namespace App\Actions;

use App\Models\World;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResolveUserActivity
{
    public const POSTURES = ['standing', 'crouching', 'sitting', 'lying', 'reclining', 'swimming'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'userState' => ['nullable', 'array'],
            'userState.posture' => ['required_with:userState', 'string', Rule::in(self::POSTURES)],
            'userState.spotId' => ['nullable', 'string', 'max:100'],
            'userState.activityId' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The user's posture, and the object and activity they are busy with, looked up in the world's layout.
     *
     * @param  ?array{posture: string, spotId?: ?string, activityId?: ?string}  $userState
     * @return ?array{posture: string, object: ?array, activity: ?array}
     *
     * @throws ValidationException
     */
    public function handle(World $world, ?array $userState): ?array
    {
        $layout = $world->layout ?? [];
        if ($userState === null || empty($layout['zones'])) {
            return null;
        }

        $object = null;
        $spot = null;
        $spotId = $userState['spotId'] ?? null;
        if ($spotId !== null) {
            foreach ($layout['objects'] ?? [] as $candidate) {
                $spot = collect($candidate['spots'])->firstWhere('id', $spotId);
                if ($spot !== null) {
                    $object = $candidate;
                    break;
                }
            }

            if ($spot === null) {
                throw ValidationException::withMessages(['userState.spotId' => 'There is no spot with this id in the world.']);
            }
        }

        $activity = null;
        $activityId = $userState['activityId'] ?? null;
        if ($activityId !== null) {
            $candidates = $spot !== null
                ? collect($spot['activities'])
                : collect($layout['zones'])->flatMap(fn (array $zone) => $zone['activities'] ?? []);
            $activity = $candidates->firstWhere('id', $activityId);

            if ($activity === null) {
                throw ValidationException::withMessages(['userState.activityId' => $spot !== null
                    ? 'This spot does not offer that activity.'
                    : 'No place in the world offers that activity.']);
            }
        }

        return ['posture' => $userState['posture'], 'object' => $object, 'activity' => $activity];
    }
}
