<?php

namespace App\Services\AgentLoop\Tools\World;

class ThinkTool extends WorldTool
{
    public function name(): string
    {
        return 'think';
    }

    public function description(): string
    {
        return 'Lets your mind turn to something for a while, right where you are: the place, the user, someone here, your day, anything on your mind. You stay as you are; your line carries the thought.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'about' => ['type' => 'string', 'description' => 'What you think about, in a few words.'],
            ],
            'required' => ['about'],
        ];
    }

    public function handle(array $arguments): array
    {
        $about = trim((string) ($arguments['about'] ?? ''));
        if ($about === '') {
            throw new \RuntimeException('think needs something to think about.');
        }

        $this->toolbox->choose(['verb' => 'think', 'target' => mb_substr($about, 0, 255), 'activity' => null]);

        return ['status' => 'started', 'note' => "You stay as you are, thinking about {$about}."];
    }
}
