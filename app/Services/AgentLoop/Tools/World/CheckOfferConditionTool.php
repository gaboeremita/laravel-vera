<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\LookUpOfferCondition;
use App\Actions\Quests\OfferMoment;
use App\Actions\Quests\QuestSessionState;
use App\Contracts\AgentTool;
use App\Models\WorldSessionQuest;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lets a giver check one part of what a task they could offer asks, before
 * deciding in character whether to offer it. It reports the value and the
 * ask, never a verdict: the comparison is the giver's.
 */
class CheckOfferConditionTool implements AgentTool
{
    /** @var array<int, array{quest: string, part: string, value: string, asks: string}> every lookup this turn, in order */
    public array $lookups = [];

    public function __construct(
        private readonly OfferQuestTool $offerQuest,
        private readonly OfferMoment $moment,
    ) {}

    public function name(): string
    {
        return 'check_offer_condition';
    }

    public function description(): string
    {
        return 'Checks one part of what a task you could offer asks for: you get how it stands right now and what the task asks. Check the parts you need before deciding whether to offer; you decide.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'quest' => ['type' => 'string', 'enum' => $this->checkable()->keys()->values()->all(), 'description' => 'The task.'],
                'part' => ['type' => 'string', 'enum' => $this->checkable()->flatMap(fn (WorldSessionQuest $run) => $this->lookUp()->parts($run->quest, $this->moment->giver)->pluck('label'))->unique()->values()->all(), 'description' => 'The part of it to check.'],
            ],
            'required' => ['quest', 'part'],
        ];
    }

    public function handle(array $arguments): array
    {
        $title = trim((string) ($arguments['quest'] ?? ''));
        $run = $this->checkable()->get($title) ?? throw new RuntimeException('That isn\'t a task you can offer right now.');

        try {
            $result = $this->lookUp()->lookUp($run, trim((string) ($arguments['part'] ?? '')), QuestSessionState::for($this->moment->session), $this->moment);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }

        $this->lookups[] = ['quest' => $title, ...$result];

        return $result;
    }

    /**
     * The tasks this giver can offer right now that ask something before
     * being offered, by title.
     *
     * @return Collection<string, WorldSessionQuest>
     */
    public function checkable(): Collection
    {
        return $this->offerQuest->offerable()->filter(fn (WorldSessionQuest $run) => $run->quest->offerWhen() !== null);
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }

    private function lookUp(): LookUpOfferCondition
    {
        return app(LookUpOfferCondition::class);
    }
}
