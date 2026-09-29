<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;
use App\Enums\TurnMode;
use App\Models\ResidentFeeling;

/**
 * Changes how the resident feels about the user. In character each change is
 * at most 3 points; out of character and in creator mode the user may set any
 * value, so the change can reach across the whole range.
 */
class AdjustFeelingsTool implements AgentTool
{
    private const IN_CHARACTER_STEP = 3;

    public function __construct(
        private readonly ResidentFeeling $feeling,
        private readonly TurnMode $mode,
    ) {}

    public function name(): string
    {
        return 'adjust_feelings';
    }

    public function description(): string
    {
        return 'Changes how you feel about the user: romance, trust and liking. Give only the feelings that change, as the amount to add (negative to lower), and your reason.';
    }

    public function parameters(): array
    {
        $step = $this->step();
        $change = ['type' => 'number', 'minimum' => -$step, 'maximum' => $step];

        return [
            'type' => 'object',
            'properties' => [
                'romance' => $change,
                'trust' => $change,
                'liking' => $change,
                'reason' => ['type' => 'string', 'description' => 'Why you feel differently now.'],
            ],
            'required' => ['reason'],
        ];
    }

    public function handle(array $arguments): array
    {
        $step = $this->step();
        $changes = collect(ResidentFeeling::FEELINGS)
            ->filter(fn (string $feeling) => is_numeric($arguments[$feeling] ?? null))
            ->mapWithKeys(fn (string $feeling) => [$feeling => max(-$step, min($step, (float) $arguments[$feeling]))])
            ->all();

        if ($changes === []) {
            return ['status' => 'unchanged', 'note' => 'Name at least one feeling to change.'];
        }

        $this->feeling->adjust($changes);

        return ['status' => 'adjusted', 'feelings' => $this->feeling->values(), 'note' => 'Done; carry on.'];
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
        return $this->mode === TurnMode::InCharacter ? self::IN_CHARACTER_STEP : 2 * ResidentFeeling::LIMIT;
    }
}
