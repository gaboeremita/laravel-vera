<?php

namespace App\Actions;

use App\Models\World;
use App\Models\WorldResident;
use Illuminate\Validation\ValidationException;

class ResolveSpotStacking
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'stackedSpots' => ['nullable', 'array', 'max:50'],
            'stackedSpots.*.spotId' => ['required', 'string', 'max:100'],
            'stackedSpots.*.holders' => ['required', 'array', 'min:2', 'max:10'],
            'stackedSpots.*.holders.*' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Who lies on top of the resident, and whom she lies on top of, in shared
     * spots. Holders are listed bottom first, as "user" or a resident id.
     *
     * @param  array<int, array{spotId: string, holders: array<int, string>}>  $stackedSpots
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    public function handle(World $world, WorldResident $resident, array $stackedSpots): array
    {
        $names = $world->residents()->with('assistant')->get()
            ->mapWithKeys(fn (WorldResident $candidate) => [(string) $candidate->id => $candidate->assistant->name])
            ->put('user', 'the user');
        $self = (string) $resident->id;
        $phrases = [];

        foreach ($stackedSpots as $index => ['spotId' => $spotId, 'holders' => $holders]) {
            $position = array_search($self, $holders, true);
            if ($position === false) {
                continue;
            }

            $object = $this->objectWithSpot($world, $spotId);
            if ($object === null) {
                throw ValidationException::withMessages(["stackedSpots.{$index}.spotId" => 'There is no spot with this id in the world.']);
            }

            $below = $holders[$position - 1] ?? null;
            $above = $holders[$position + 1] ?? null;
            if ($below !== null && $names->has($below)) {
                $phrases[] = "you are lying on top of {$names[$below]} on the {$object['name']}";
            }
            if ($above !== null && $names->has($above)) {
                $phrases[] = "{$names[$above]} is lying on top of you on the {$object['name']}";
            }
        }

        return $phrases;
    }

    private function objectWithSpot(World $world, string $spotId): ?array
    {
        foreach ($world->layout['objects'] ?? [] as $object) {
            if (collect($object['spots'])->contains('id', $spotId)) {
                return $object;
            }
        }

        return null;
    }
}
