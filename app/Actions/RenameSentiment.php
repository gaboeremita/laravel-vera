<?php

namespace App\Actions;

use App\Models\ActivityTerms;
use App\Models\Assistant;
use App\Models\Conversation;
use App\Models\Sentiment;
use App\Models\User;
use App\Models\World;
use App\Models\WorldSession;
use Illuminate\Database\Eloquent\Builder;

/**
 * Carries each renamed sentiment of a world or an assistant over to
 * everything that names it: the scores of the conversations that use its
 * definitions and, for a world, the sentiment conditions of its quests and
 * activity responses. Renames go through a temporary name first so two
 * sentiments can swap.
 */
class RenameSentiment
{
    /**
     * @param  array<int, array{name: string, renamedFrom?: ?string}>  $definitions  the definitions being saved
     */
    public function handle(World|Assistant $holder, array $definitions): void
    {
        $renames = collect($definitions)
            ->filter(fn (array $definition) => in_array($definition['renamedFrom'] ?? null, $holder->sentimentNames(), true) && $definition['renamedFrom'] !== trim($definition['name']))
            ->mapWithKeys(fn (array $definition) => [$definition['renamedFrom'] => trim($definition['name'])]);

        $renames->keys()->each(fn (string $from, int $index) => $this->rename($holder, $from, "__rename{$index}__"));
        $renames->values()->each(fn (string $to, int $index) => $this->rename($holder, "__rename{$index}__", $to));
    }

    private function rename(World|Assistant $holder, string $from, string $to): void
    {
        Sentiment::whereIn('conversation_id', $this->conversations($holder)->select('id'))->get()
            ->filter(fn (Sentiment $sentiment) => array_key_exists($from, $sentiment->values ?? []))
            ->each(function (Sentiment $sentiment) use ($from, $to) {
                $values = $sentiment->values;
                $values[$to] = $values[$from];
                unset($values[$from]);
                $sentiment->update(['values' => $values]);
            });

        if ($holder instanceof Assistant) {
            return;
        }

        foreach ($holder->quests()->get() as $quest) {
            $definition = $this->renameIn($quest->definition, $from, $to);
            if ($definition !== $quest->definition) {
                $quest->update(['definition' => $definition]);
            }
        }

        ActivityTerms::whereIn('region_id', $holder->regions()->select('id'))->get()
            ->each(function (ActivityTerms $terms) use ($from, $to) {
                $responses = $this->renameIn($terms->responses, $from, $to);
                if ($responses !== $terms->responses) {
                    $terms->update(['responses' => $responses]);
                }
            });
    }

    /**
     * @return Builder<Conversation>
     */
    private function conversations(World|Assistant $holder): Builder
    {
        if ($holder instanceof World) {
            return Conversation::whereIn('world_session_id', WorldSession::whereIn('world_user_id', $holder->worldUsers()->select('id'))->select('id'));
        }

        return Conversation::whereNull('world_session_id')
            ->where('owner_type', (new User)->getMorphClass())
            ->whereMorphedTo('counterpart', $holder);
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
