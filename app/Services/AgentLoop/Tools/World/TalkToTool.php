<?php

namespace App\Services\AgentLoop\Tools\World;

use RuntimeException;

class TalkToTool extends WorldTool
{
    public function name(): string
    {
        return 'talk_to';
    }

    public function description(): string
    {
        return 'Starts talking to someone in person who is in the same room as you: the user, or another resident by name. You talk from where you are when they are close enough to talk to, and walk over to them only when they are farther away, saying your opening line when you get there. With a resident you have talked to before, your conversation picks up where it stopped.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'target' => [
                    'type' => 'string',
                    'enum' => $this->targets(),
                    'description' => '"user" for the user, or the name of the resident you talk to.',
                ],
                'line' => [
                    'type' => 'string',
                    'description' => 'What you say to them first, in your own voice.',
                ],
            ],
            'required' => ['target', 'line'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function targets(): array
    {
        return [...($this->toolbox->userAvailable && $this->toolbox->userInSight ? [WorldToolbox::USER_TARGET] : []), ...array_keys($this->toolbox->companions ?? [])];
    }

    public function handle(array $arguments): array
    {
        $target = trim((string) ($arguments['target'] ?? ''));
        $line = trim((string) ($arguments['line'] ?? ''));

        if ($line === '') {
            throw new RuntimeException('Say what you tell them first in "line".');
        }

        if ($target === WorldToolbox::USER_TARGET && ! $this->toolbox->userInSight) {
            throw new RuntimeException('The user is out of sight; you only talk to someone in the same room as you.');
        }

        if ($target === WorldToolbox::USER_TARGET && ! $this->toolbox->userAvailable) {
            throw new RuntimeException('The user is busy talking with someone else right now.');
        }

        if ($target === WorldToolbox::USER_TARGET) {
            $this->toolbox->choose(['verb' => 'talk_to', 'target' => WorldToolbox::USER_TARGET, 'activity' => null, 'line' => $line]);

            return ['status' => 'started', 'note' => 'You go to the user and speak to them.'];
        }

        $residentId = collect($this->toolbox->companions ?? [])->first(fn (int $id, string $name) => mb_strtolower($name) === mb_strtolower($target));
        if ($residentId === null) {
            throw new RuntimeException(sprintf('There is nobody called "%s" in this room with you. You can talk to: %s.', $target, implode(', ', $this->targets())));
        }

        $this->toolbox->choose(['verb' => 'talk_to', 'target' => (string) $residentId, 'activity' => null, 'line' => $line]);

        return ['status' => 'started', 'note' => "You go to {$target} and start talking."];
    }
}
