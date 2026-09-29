<?php

namespace App\Services\AgentLoop\Tools;

use App\Contracts\AgentTool;
use App\Jobs\GenerateAvatarBackground;
use App\Models\AssistantUser;
use App\Models\Conversation;

class ChangeBackgroundTool implements AgentTool
{
    public function __construct(
        private readonly AssistantUser $assistantUser,
        private readonly Conversation $conversation,
    ) {}

    public function name(): string
    {
        return 'change_background';
    }

    public function description(): string
    {
        return 'Changes the background behind your 3D avatar to a described setting. Use when the user asks to change the background, the scenery, or the backdrop behind you. The new background appears a little while after the call.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'description' => [
                    'type' => 'string',
                    'description' => 'The setting to show, such as a place from the current scene.',
                ],
            ],
            'required' => ['description'],
        ];
    }

    public function handle(array $arguments): array
    {
        $description = trim((string) ($arguments['description'] ?? ''));

        if ($description === '') {
            throw new \RuntimeException('Missing required "description" argument.');
        }

        GenerateAvatarBackground::dispatchFor($this->assistantUser, $this->conversation, $description);

        return [
            'status' => 'queued',
            'description' => $description,
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
