<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Contracts\AgentTool;

/**
 * The ending a quest or campaign came to, written against its author's
 * rubric. Its call is read directly from the reply; tiers, dimensions and,
 * when later quests look for some, resulting flags can only be the names the
 * author gave.
 */
class RecordEndingTool implements AgentTool
{
    /**
     * @param  array<int, string>  $dimensions
     * @param  array<int, string>  $tiers
     * @param  array<int, string>  $watchedFlags  flags later quests look for
     */
    public function __construct(
        private readonly array $dimensions,
        private readonly array $tiers,
        private readonly array $watchedFlags,
    ) {}

    public function name(): string
    {
        return 'record_ending';
    }

    public function description(): string
    {
        return 'Records how the story ended: its tier, a title, an epilogue, a score for each dimension of the rubric with a reason, and the flags that result from it.';
    }

    public function parameters(): array
    {
        $flag = ['type' => 'string', ...($this->watchedFlags !== [] ? ['enum' => $this->watchedFlags] : [])];

        return [
            'type' => 'object',
            'properties' => [
                ...($this->tiers !== [] ? ['tier' => ['type' => 'string', 'enum' => $this->tiers, 'description' => 'The tier that fits how it went.']] : []),
                'title' => ['type' => 'string', 'description' => 'A short title for this ending.'],
                'epilogue' => ['type' => 'string', 'description' => 'Two to five paragraphs telling how it ended and what it left behind, addressed to the user.'],
                'scores' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'dimension' => ['type' => 'string', 'enum' => $this->dimensions],
                            'score' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                            'reason' => ['type' => 'string', 'description' => 'One or two sentences on why.'],
                        ],
                        'required' => ['dimension', 'score', 'reason'],
                    ],
                ],
                'resultingFlags' => ['type' => 'array', 'items' => $flag, 'description' => 'What is now true in the world because of how it ended.'],
            ],
            'required' => [...($this->tiers !== [] ? ['tier'] : []), 'title', 'epilogue', 'scores', 'resultingFlags'],
        ];
    }

    /**
     * @return array{tier: ?string, title: string, epilogue: string, scores: array<int, array{dimension: string, score: int, reason: string}>, resultingFlags: array<int, string>}
     */
    public function handle(array $arguments): array
    {
        $tier = $arguments['tier'] ?? null;

        return [
            'tier' => in_array($tier, $this->tiers, true) ? $tier : null,
            'title' => trim((string) ($arguments['title'] ?? '')),
            'epilogue' => trim((string) ($arguments['epilogue'] ?? '')),
            'scores' => collect($arguments['scores'] ?? [])
                ->filter(fn ($score) => is_array($score) && in_array($score['dimension'] ?? null, $this->dimensions, true))
                ->unique('dimension')
                ->map(fn (array $score) => ['dimension' => $score['dimension'], 'score' => max(1, min(10, (int) ($score['score'] ?? 1))), 'reason' => trim((string) ($score['reason'] ?? ''))])
                ->values()
                ->all(),
            'resultingFlags' => collect($arguments['resultingFlags'] ?? [])
                ->filter(fn ($flag) => is_string($flag) && preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $flag) === 1)
                ->when($this->watchedFlags !== [], fn ($flags) => $flags->intersect($this->watchedFlags))
                ->unique()
                ->values()
                ->all(),
        ];
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
