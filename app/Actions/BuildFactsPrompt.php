<?php

namespace App\Actions;

use App\Enums\RevealSource;
use App\Enums\TurnMode;
use App\Models\Fact;
use App\Models\KnownFact;
use App\Models\WorldResident;
use App\Models\WorldSession;
use Illuminate\Support\Collection;

/**
 * The secrets a resident keeps and the ones they want to learn, for their
 * own prompt. A secret's content is here only once the player knows it, or on
 * out-of-character and creator turns.
 */
class BuildFactsPrompt
{
    public function handle(WorldSession $session, WorldResident $resident, TurnMode $mode): ?string
    {
        $known = $session->knownFacts()->get()->keyBy('fact_id');

        if ($mode === TurnMode::Creator) {
            return $this->everyFact($resident);
        }

        $parts = array_filter([
            $this->held($resident->facts()->orderBy('topic')->get(), $known, $mode),
            $this->relayed($resident->relayedFacts()->orderBy('topic')->get(), $known, $mode),
        ]);

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /**
     * @param  Collection<int, Fact>  $facts
     * @param  Collection<int, KnownFact>  $known
     */
    private function held(Collection $facts, Collection $known, TurnMode $mode): ?string
    {
        if ($facts->isEmpty()) {
            return null;
        }

        if ($mode === TurnMode::BetweenResidents) {
            return "You keep these secrets for the user alone; with other residents you keep them to yourself:\n"
                .$facts->map(fn (Fact $fact) => "- {$fact->topic}. When you share it with the user: {$fact->disclosure}")->implode("\n");
        }

        $lines = $facts->map(function (Fact $fact) use ($known, $mode): string {
            $knownFact = $known->get($fact->id);
            if ($knownFact !== null && in_array($knownFact->source, [RevealSource::InCharacter, RevealSource::OocTurn], true)) {
                return "- {$fact->topic}. You have told the user: {$fact->content} Speak of it freely.";
            }
            if ($knownFact !== null) {
                return "- {$fact->topic}. The user found out through {$knownFact->source_name}: {$fact->content} Talk about it once they bring it up.";
            }
            if ($mode === TurnMode::OocTurn) {
                return "- {$fact->topic}. What you know: {$fact->content} When you share it in the story: {$fact->disclosure}";
            }

            return "- {$fact->topic}. When you share it: {$fact->disclosure}";
        });

        return "You keep these secrets. Each one lists what it is about and when you share it. When the moment comes, call the reveal tool with your reason; it gives you what you know, and you tell it in your own words.\n"
            .$lines->implode("\n");
    }

    /**
     * @param  Collection<int, Fact>  $facts
     * @param  Collection<int, KnownFact>  $known
     */
    private function relayed(Collection $facts, Collection $known, TurnMode $mode): ?string
    {
        $learned = $mode->withUser() ? $facts->filter(fn (Fact $fact) => $known->has($fact->id)) : collect();
        $wanted = $facts->reject(fn (Fact $fact) => $learned->contains($fact));

        return implode("\n\n", array_filter([
            $wanted->isEmpty() ? null : "You want to find out about these things; ask about them when it suits the moment:\n"
                .$wanted->map(fn (Fact $fact) => "- {$fact->topic}")->implode("\n"),
            $learned->isEmpty() ? null : "The user has learned these; when they tell you one of them, call the acknowledge tool and act on it:\n"
                .$learned->map(fn (Fact $fact) => "- {$fact->topic}: {$fact->content}")->implode("\n"),
        ])) ?: null;
    }

    private function everyFact(WorldResident $resident): ?string
    {
        $facts = Fact::with('holder.assistant')->whereHas('holder', fn ($query) => $query->where('world_id', $resident->world_id))->orderBy('topic')->get();

        return $facts->isEmpty() ? null : "The creator of this world is directing you. You know every secret in it, and you reveal any of them with the reveal tool when they ask:\n"
            .$facts->map(fn (Fact $fact) => "- {$fact->label()}: {$fact->content}")->implode("\n");
    }
}
