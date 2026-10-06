<?php

namespace App\Actions;

use App\Models\ActivityTerms;
use App\Models\ResidentSentiment;
use App\Models\World;
use App\Models\WorldResident;

/**
 * Carries a renamed sentiment of a world over to everything that names it:
 * the residents' scores in every session, and the sentiment conditions of
 * the world's quests and activity responses.
 */
class RenameSentiment
{
    public function handle(World $world, string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        ResidentSentiment::whereIn('world_resident_id', WorldResident::where('world_id', $world->id)->select('id'))->get()
            ->filter(fn (ResidentSentiment $sentiment) => array_key_exists($from, $sentiment->values ?? []))
            ->each(function (ResidentSentiment $sentiment) use ($from, $to) {
                $values = $sentiment->values;
                $values[$to] = $values[$from];
                unset($values[$from]);
                $sentiment->update(['values' => $values]);
            });

        foreach ($world->quests()->get() as $quest) {
            $definition = $this->renameIn($quest->definition, $from, $to);
            if ($definition !== $quest->definition) {
                $quest->update(['definition' => $definition]);
            }
        }

        ActivityTerms::whereIn('region_id', $world->regions()->select('id'))->get()
            ->each(function (ActivityTerms $terms) use ($from, $to) {
                $responses = $this->renameIn($terms->responses, $from, $to);
                if ($responses !== $terms->responses) {
                    $terms->update(['responses' => $responses]);
                }
            });
    }

    private function renameIn(mixed $node, string $from, string $to): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        foreach ($node as $key => $child) {
            $node[$key] = $key === 'sentiment' && is_array($child) && ($child['kind'] ?? null) === $from
                ? [...$child, 'kind' => $to]
                : $this->renameIn($child, $from, $to);
        }

        return $node;
    }
}
