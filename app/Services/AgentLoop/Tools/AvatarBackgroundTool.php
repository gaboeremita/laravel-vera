<?php

namespace App\Services\AgentLoop\Tools;

use App\Contracts\AgentTool;
use App\Jobs\GenerateAvatarBackground;
use App\Models\AssistantUser;
use App\Models\Conversation;

class AvatarBackgroundTool implements AgentTool
{
    public function __construct(
        private readonly AssistantUser $assistantUser,
        private readonly Conversation $conversation,
    ) {}

    public function name(): string
    {
        return 'change_avatar_background';
    }

    public function description(): string
    {
        return "Changes the visible background scene behind your 3D avatar. Use when the user asks to change, set, or update the background/scene/setting, or when the conversation's setting has clearly moved somewhere new.";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'description' => [
                    'type' => 'string',
                    'description' => 'A description of the new setting/location for the background.',
                ],
            ],
            'required' => ['description'],
        ];
    }

    public function handle(array $arguments): array
    {
        if (empty($arguments['description'])) {
            throw new \RuntimeException('Missing required "description" argument.');
        }

        GenerateAvatarBackground::dispatchFor($this->assistantUser, $this->conversation, $arguments['description']);

        return [
            'status' => 'queued',
            'description' => $arguments['description'],
        ];
    }

    public function timeoutSeconds(): int
    {
        return 5;
    }

    public function retryAttempts(): int
    {
        return 1;
    }
}
