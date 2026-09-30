<?php

namespace App\Actions\Activities;

use App\Models\Fact;
use App\Models\World;
use App\Models\WorldResident;

/**
 * Checks a list of responses against its shape and the world it belongs to,
 * reporting each problem at the path it sits at, and puts it in the form it
 * is stored in.
 */
class ValidateActivityResponses
{
    private const FEELINGS = ['romance', 'trust', 'liking'];

    private const QUEST_STATES = ['offered', 'active', 'declined', 'abandoned'];

    private const MAX_DEPTH = 4;

    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /** @var array<int, int> */
    private array $itemIds = [];

    /** @var array<int, int> */
    private array $residentIds = [];

    /** @var array<int, int> */
    private array $factIds = [];

    public function __construct(private readonly ActivityEffects $effects) {}

    /**
     * @param  array<int, mixed>  $responses
     * @return array<string, array<int, string>> messages by path
     */
    public function errors(array $responses, World $world): array
    {
        $this->errors = [];
        $this->itemIds = $world->items()->pluck('id')->all();
        $this->residentIds = $world->residents()->pluck('id')->all();
        $this->factIds = Fact::whereIn('world_resident_id', WorldResident::where('world_id', $world->id)->select('id'))->pluck('id')->all();

        foreach (array_values($responses) as $index => $response) {
            $path = "responses.{$index}";
            if (! is_array($response)) {
                $this->add($path, 'Each response must be an object.');

                continue;
            }
            $this->condition($response['condition'] ?? null, "{$path}.condition", 0, true);
            $effects = $response['effects'] ?? [];
            if (! is_array($effects)) {
                $this->add("{$path}.effects", 'Effects must be a list.');

                continue;
            }
            foreach (array_values($effects) as $effectIndex => $entry) {
                $effectPath = "{$path}.effects.{$effectIndex}";
                $effect = is_array($entry) ? $this->effects->find((string) ($entry['type'] ?? '')) : null;
                if ($effect === null) {
                    $this->add($effectPath, 'Unknown effect.');

                    continue;
                }
                foreach ($effect->validate($entry, $world) as $message) {
                    $this->add($effectPath, $message);
                }
            }
        }

        return $this->errors;
    }

    /**
     * The responses as stored: each effect keeps its type and what it reads.
     *
     * @param  array<int, array<string, mixed>>  $responses
     * @return array<int, array{condition: ?array, effects: array<int, array<string, mixed>>}>
     */
    public function normalize(array $responses): array
    {
        return collect($responses)->map(fn (array $response) => [
            'condition' => filled($response['condition'] ?? null) ? $response['condition'] : null,
            'effects' => collect($this->effects->resolve($response['effects'] ?? []))
                ->map(fn (array $pair) => ['type' => $pair[0]->type(), ...$pair[0]->normalize($pair[1])])
                ->all(),
        ])->values()->all();
    }

    /**
     * @param  bool  $topLevel  whether the node is the condition itself or one of the top `all`'s children, the only places a narrator check may sit
     */
    private function condition(mixed $node, string $path, int $depth, bool $topLevel): void
    {
        if ($node === null) {
            return;
        }
        if (! is_array($node) || count($node) !== 1) {
            $this->add($path, 'A condition must have exactly one kind.');

            return;
        }
        if ($depth > self::MAX_DEPTH) {
            $this->add($path, 'Conditions nest too deep.');

            return;
        }

        $kind = array_key_first($node);
        $value = $node[$kind];

        if (in_array($kind, ['all', 'any'], true)) {
            if (! is_array($value) || ! array_is_list($value)) {
                $this->add($path, 'A group must be a list of conditions.');

                return;
            }
            foreach ($value as $index => $child) {
                $this->condition($child, "{$path}.{$kind}.{$index}", $depth + 1, $topLevel && $depth === 0 && $kind === 'all');
            }

            return;
        }
        if ($kind === 'not') {
            $this->condition($value, "{$path}.not", $depth + 1, false);

            return;
        }
        if (! in_array($kind, ActivityConditions::LEAVES, true)) {
            $this->add($path, "Unknown condition \"{$kind}\".");

            return;
        }
        if ($kind === 'narrator' && ! $topLevel) {
            $this->add($path, 'A narrator check can only be one of the conditions that must all be met.');

            return;
        }

        $problem = $this->leafProblem($kind, $value);
        if ($problem !== null) {
            $this->add($path, $problem);
        }
    }

    private function leafProblem(string $kind, mixed $value): ?string
    {
        $isArray = is_array($value);

        return match ($kind) {
            'has' => $isArray && $this->known($this->itemIds, $value['item'] ?? null) && $this->atLeast($value, 1) ? null : 'Choose one of the world\'s items and a quantity of at least 1.',
            'credits' => $isArray && $this->atLeast($value, 0) ? null : 'The amount must be a whole number of at least 0.',
            'knows' => $this->known($this->factIds, $value) ? null : 'Choose one of the world\'s facts.',
            'acknowledged' => $isArray && $this->known($this->factIds, $value['fact'] ?? null) && $this->known($this->residentIds, $value['resident'] ?? null) ? null : 'Choose one of the world\'s facts and residents.',
            'flag' => $isArray && filled($value['quest'] ?? null) && filled($value['name'] ?? null) ? null : 'Choose a quest and the flag it ended with.',
            'feeling' => $isArray && $this->known($this->residentIds, $value['resident'] ?? null) && in_array($value['kind'] ?? null, self::FEELINGS, true) && (isset($value['atLeast']) || isset($value['atMost'])) ? null : 'Choose a resident, a feeling and a bound.',
            'questState' => $isArray && filled($value['quest'] ?? null) && in_array($value['state'] ?? null, self::QUEST_STATES, true) ? null : 'Choose a quest and a state.',
            'declinedTimes' => $isArray && filled($value['quest'] ?? null) && $this->atLeast($value, 1) ? null : 'Choose a quest and a count of at least 1.',
            'gaveTo' => $isArray && $this->known($this->residentIds, $value['resident'] ?? null) && $this->known($this->itemIds, $value['item'] ?? null) && $this->atLeast($value, 1) ? null : 'Choose a resident, an item and a quantity of at least 1.',
            'spentWith' => $isArray && $this->known($this->residentIds, $value['resident'] ?? null) && $this->atLeast($value, 1) ? null : 'Choose a resident and an amount of at least 1.',
            'objectState' => is_string($value) && $value !== '' ? null : 'Name the state.',
            'narrator' => $isArray && (filled($value['requirement'] ?? null) || filled($value['outcome'] ?? null)) ? null : 'Write the requirement or the outcome for the narrator.',
            default => null,
        };
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function known(array $ids, mixed $id): bool
    {
        return is_int($id) && in_array($id, $ids, true);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function atLeast(array $value, int $minimum): bool
    {
        return is_int($value['atLeast'] ?? null) && $value['atLeast'] >= $minimum;
    }

    private function add(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }
}
