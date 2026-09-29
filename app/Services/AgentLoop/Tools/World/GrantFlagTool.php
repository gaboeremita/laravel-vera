<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\RecordQuestEvent;
use App\Contracts\AgentTool;
use App\Enums\QuestEventType;
use App\Enums\QuestStatus;
use App\Events\Quests\QuestFlagChanged;
use App\Models\WorldResident;
use App\Models\WorldSession;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * A resident grants a flag a current beat lets them grant, deciding in
 * character that the player has earned it.
 */
class GrantFlagTool implements AgentTool
{
    public function __construct(
        private readonly WorldSession $session,
        private readonly WorldResident $resident,
    ) {}

    public function name(): string
    {
        return 'grant_flag';
    }

    public function description(): string
    {
        return 'Grants the user progress in a story you are part of, once you decide in character that they have earned it. Give your reason.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'flag' => ['type' => 'string', 'enum' => $this->grantable()->keys()->values()->all(), 'description' => 'What they earned.'],
                'reason' => ['type' => 'string', 'description' => 'Why they have earned it, in a sentence.'],
            ],
            'required' => ['flag', 'reason'],
        ];
    }

    public function handle(array $arguments): array
    {
        $flag = trim((string) ($arguments['flag'] ?? ''));
        $grants = $this->grantable()->get($flag) ?? throw new RuntimeException('That isn\'t yours to grant right now.');
        $reason = trim((string) ($arguments['reason'] ?? '')) ?: 'no reason given';

        foreach ($grants as ['run' => $run, 'beat' => $beatId]) {
            $run->mergeState(['flags' => [...($run->state['flags'] ?? []), $flag => ['by' => $this->resident->id, 'reason' => $reason]]]);
            $run->save();
            app(RecordQuestEvent::class)->handle($run, QuestEventType::FlagSet, $beatId, ['flag' => $flag, 'reason' => $reason, 'residentId' => $this->resident->id, 'residentName' => $this->resident->assistant->name]);
        }

        QuestFlagChanged::dispatch($this->session->id);

        return ['status' => 'granted', 'note' => 'Done; carry on in character.'];
    }

    /**
     * The flags current beats let this resident grant and that aren't set
     * yet, each with the runs and beats that allow it.
     *
     * @return Collection<string, array<int, array{run: WorldSessionQuest, beat: string}>>
     */
    public function grantable(): Collection
    {
        return $this->session->questRuns()->with('quest')->where('status', QuestStatus::Active)->get()
            ->flatMap(fn (WorldSessionQuest $run) => collect($run->currentBeats())
                ->flatMap(fn (array $beat) => collect($beat['grants'] ?? [])
                    ->where('resident', $this->resident->id)
                    ->reject(fn (array $grant) => $run->hasFlag($grant['flag']))
                    ->map(fn (array $grant) => ['flag' => $grant['flag'], 'run' => $run, 'beat' => $beat['id']])))
            ->groupBy('flag')
            ->map(fn (Collection $grants) => $grants->map(fn (array $grant) => ['run' => $grant['run'], 'beat' => $grant['beat']])->all());
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
