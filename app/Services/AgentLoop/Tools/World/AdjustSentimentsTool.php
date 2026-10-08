<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Enums\TurnMode;
use App\Models\ResidentSentiment;

/**
 * Changes how the resident feels about the user, one amount per sentiment of
 * the world. In character each change is at most 3 points; out of character
 * and in creator mode the user may set any value, so the change can reach
 * across the whole range.
 */
class AdjustSentimentsTool implements AgentTool
{
    private const IN_CHARACTER_STEP = 3;

    public function __construct(
        private readonly ResidentSentiment $sentiment,
        private readonly TurnMode $mode,
    ) {}

    public function name(): string
    {
        return 'adjust_sentiments';
    }

    public function description(): string
    {
        $names = implode(', ', $this->sentiment->names());

        return "Changes how you feel about the user: {$names}. Give only the ones that change, as the amount to add (negative to lower), and your reason.";
    }

    public function parameters(): array
    {
        $step = $this->step();
        $change = ['type' => 'number', 'minimum' => -$step, 'maximum' => $step];

        return [
            'type' => 'object',
            'properties' => [
                ...collect($this->sentiment->names())->mapWithKeys(fn (string $name) => [$name => $change])->all(),
                'reason' => ['type' => 'string', 'description' => 'Why you feel differently now.'],
            ],
            'required' => ['reason'],
        ];
    }

    public function handle(array $arguments): array
    {
        $step = $this->step();
        $changes = collect($this->sentiment->names())
            ->filter(fn (string $name) => is_numeric($arguments[$name] ?? null))
            ->mapWithKeys(fn (string $name) => [$name => max(-$step, min($step, (float) $arguments[$name]))])
            ->all();

        if ($changes === []) {
            return ['status' => 'unchanged', 'note' => 'Name at least one sentiment to change.'];
        }

        $this->sentiment->adjust($changes);

        return ['status' => 'adjusted', 'sentiments' => $this->sentiment->scores(), 'note' => 'Done; carry on.'];
    }

    public function timeoutSeconds(): int
    {
        return config('agent.tool_timeout');
    }

    public function retryAttempts(): int
    {
        return 1;
    }

    private function step(): int
    {
        return $this->mode === TurnMode::InCharacter ? self::IN_CHARACTER_STEP : 2 * ResidentSentiment::LIMIT;
    }
}
