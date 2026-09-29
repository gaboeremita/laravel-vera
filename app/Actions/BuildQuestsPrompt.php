<?php

namespace App\Actions;

use App\Enums\EndingStatus;
use App\Enums\QuestOfferStatus;
use App\Enums\QuestStatus;
use App\Enums\TurnMode;
use App\Models\Quest;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;

/**
 * A resident's part in the session's quests, for their own prompt: what the
 * author wants them to know while a beat is current, what they can grant,
 * signal or offer while talking with the user, and the endings they remember.
 * Endings stay in this session's quest data, so no other session or chat
 * outside the world ever hears of them.
 */
class BuildQuestsPrompt
{
    /**
     * @param  bool  $canUseTools  whether their model can call tools; granting, signalling and offering need them
     */
    public function handle(WorldSession $session, WorldResident $resident, TurnMode $mode, bool $canUseTools = true): ?string
    {
        $runs = $session->questRuns()->with('quest')->orderBy('id')->get();
        $withUser = $mode->withUser() && $canUseTools;
        $pendingRunIds = $session->questOffers()->where('world_resident_id', $resident->id)->where('status', QuestOfferStatus::Pending)->pluck('world_session_quest_id')->all();

        $parts = array_filter([
            $this->knowledge($runs, $resident),
            $this->pendingOffers($runs, $pendingRunIds),
            $this->underway($runs, $resident),
            $withUser ? $this->grants($runs, $resident) : null,
            $withUser ? $this->questions($runs, $resident) : null,
            $withUser ? $this->offers($runs, $resident, $pendingRunIds) : null,
            $this->endings($runs, $resident),
            $mode === TurnMode::Creator ? $this->everything($session, $runs) : null,
        ]);

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /**
     * @param  Collection<int, WorldSessionQuest>  $runs
     */
    private function knowledge(Collection $runs, WorldResident $resident): ?string
    {
        $lines = $runs->flatMap(fn (WorldSessionQuest $run) => collect($run->currentBeats())
            ->flatMap(fn (array $beat) => collect($beat['knowledge'] ?? [])
                ->where('resident', $resident->id)
                ->map(fn (array $knowledge) => "- {$run->quest->title}: {$knowledge['prose']}")));

        return $lines->isEmpty() ? null : "You are part of stories happening around the user right now. Play your part as written here:\n".$lines->implode("\n");
    }

    /**
     * @param  Collection<int, WorldSessionQuest>  $runs
     */
    private function grants(Collection $runs, WorldResident $resident): ?string
    {
        $lines = $runs->flatMap(fn (WorldSessionQuest $run) => collect($run->currentBeats())
            ->flatMap(fn (array $beat) => collect($beat['grants'] ?? [])
                ->where('resident', $resident->id)
                ->reject(fn (array $grant) => $run->hasFlag($grant['flag']))
                ->map(fn (array $grant) => "- {$grant['flag']}: for \"{$beat['text']}\" in {$run->quest->title}")))
            ->unique();

        return $lines->isEmpty() ? null : "You decide in character whether the user has earned these. The moment they truly have, call the grant_flag tool in that same reply, alongside your words, with your reason:\n".$lines->implode("\n");
    }

    /**
     * @param  Collection<int, WorldSessionQuest>  $runs
     */
    private function questions(Collection $runs, WorldResident $resident): ?string
    {
        $lines = $runs->flatMap(fn (WorldSessionQuest $run) => collect($run->currentBeats())
            ->flatMap(fn (array $beat) => collect($beat['questions'] ?? [])
                ->filter(fn (array $question) => in_array($resident->id, $question['residents'] ?? [], true) && ! $run->questionMet($question['id']))
                ->map(fn (array $question) => "- {$question['text']}")))
            ->unique();

        return $lines->isEmpty() ? null : "Keep these questions in mind and check them after every message from the user. As soon as you believe the user has done one of them, call the signal_question tool in that same reply, alongside your words, with your reason:\n".$lines->implode("\n");
    }

    /**
     * @param  Collection<int, WorldSessionQuest>  $runs
     * @param  array<int, int>  $pendingRunIds  the runs they offered that are waiting for the user's answer
     */
    private function pendingOffers(Collection $runs, array $pendingRunIds): ?string
    {
        $lines = $runs->filter(fn (WorldSessionQuest $run) => $run->status === QuestStatus::Available && in_array($run->id, $pendingRunIds, true))
            ->map(fn (WorldSessionQuest $run) => "- {$run->quest->title}: {$run->quest->description()}");

        return $lines->isEmpty() ? null : "You have asked the user to take these on and are waiting for their answer, which arrives as a bracketed line naming the task:\n".$lines->implode("\n");
    }

    /**
     * The quests they gave that the user accepted and is working on, with
     * the steps the user can see in front of them now.
     *
     * @param  Collection<int, WorldSessionQuest>  $runs
     */
    private function underway(Collection $runs, WorldResident $resident): ?string
    {
        $lines = $runs->filter(fn (WorldSessionQuest $run) => $run->status === QuestStatus::Active && $run->quest->giverId() === $resident->id)
            ->map(function (WorldSessionQuest $run): string {
                $steps = collect($run->currentBeats())->reject(fn (array $beat) => $beat['hidden'] ?? false)->pluck('text');

                return "- {$run->quest->title}: {$run->quest->description()}".($steps->isEmpty() ? '' : ' Their next step: '.$steps->implode(' '));
            });

        return $lines->isEmpty() ? null : "You asked the user for these, they accepted, and they are working on them now:\n".$lines->implode("\n");
    }

    /**
     * @param  Collection<int, WorldSessionQuest>  $runs
     * @param  array<int, int>  $pendingRunIds
     */
    private function offers(Collection $runs, WorldResident $resident, array $pendingRunIds): ?string
    {
        $lines = $runs->filter(fn (WorldSessionQuest $run) => $run->status === QuestStatus::Available && $run->quest->giverId() === $resident->id && ! in_array($run->id, $pendingRunIds, true))
            ->map(fn (WorldSessionQuest $run) => "- {$run->quest->title}: {$run->quest->description()}");

        return $lines->isEmpty() ? null : "You have tasks you can ask of the user. When it suits the conversation, bring one up in your own words and call the offer_quest tool:\n".$lines->implode("\n");
    }

    /**
     * @param  Collection<int, WorldSessionQuest>  $runs
     */
    private function endings(Collection $runs, WorldResident $resident): ?string
    {
        $lines = $runs->filter(fn (WorldSessionQuest $run) => $run->ending_status === EndingStatus::Written && in_array($resident->id, $run->involvedResidentIds(), true))
            ->map(fn (WorldSessionQuest $run) => "- {$run->quest->title}".($run->ending['tier'] ?? null ? " ({$run->ending['tier']})" : '').": {$run->ending['epilogue']}");

        return $lines->isEmpty() ? null : "You remember how these stories you were part of ended:\n".$lines->implode("\n");
    }

    /**
     * @param  Collection<int, WorldSessionQuest>  $runs
     */
    private function everything(WorldSession $session, Collection $runs): string
    {
        $latest = $runs->keyBy('quest_id');
        $lines = $session->worldUser->world->quests()->orderBy('title')->get()->map(function (Quest $quest) use ($latest): string {
            $run = $latest->get($quest->id);
            $beats = collect($quest->beats())->map(fn (array $beat) => ($run?->hasFinished($beat['id']) ? '[x] ' : '[ ] ').$beat['id']);
            $flags = $run !== null ? implode(', ', array_keys($run->state['flags'] ?? [])) : '';

            return "- {$quest->title} (".($run === null ? 'not available' : "run {$run->run}, {$run->status->value}")."): beats {$beats->implode(', ')}".($flags !== '' ? "; flags {$flags}" : '');
        });

        return "The creator of this world is directing you. These are every quest and where it stands; use the quest tools when they ask:\n".$lines->implode("\n");
    }
}
