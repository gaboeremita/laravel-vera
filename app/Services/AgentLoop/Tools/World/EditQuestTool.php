<?php

namespace App\Services\AgentLoop\Tools\World;

use App\Actions\Quests\ReconcileQuestRuns;
use App\Actions\Quests\RecordQuestEvent;
use App\Actions\Quests\ValidateQuestDefinition;
use App\Enums\QuestEventType;
use RuntimeException;

class EditQuestTool extends CreatorQuestTool
{
    public function name(): string
    {
        return 'edit_quest';
    }

    public function description(): string
    {
        return 'Replaces a quest\'s definition with the JSON the creator gives, checked like the world\'s configuration checks it.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => ['quest' => $this->questParameter(), 'definition' => ['type' => 'string', 'description' => 'The whole definition, as JSON.']],
            'required' => ['quest', 'definition'],
        ];
    }

    public function handle(array $arguments): array
    {
        $quest = $this->quest($arguments);
        $definition = json_decode((string) ($arguments['definition'] ?? ''), true);
        if (! is_array($definition)) {
            throw new RuntimeException('The definition isn\'t valid JSON.');
        }

        $result = app(ValidateQuestDefinition::class)->handle($this->session->worldUser->world, $quest->key, $definition, $quest, $this->session->worldUser->user);
        if ($result['errors'] !== []) {
            throw new RuntimeException('The definition has problems: '.collect($result['errors'])->map(fn (array $messages, string $path) => ($path === '' ? '' : "{$path}: ").implode(' ', $messages))->implode('; '));
        }

        $quest->update(['definition' => $definition]);
        app(ReconcileQuestRuns::class)->handle($quest);
        $run = $this->latestRun($quest);
        if ($run !== null) {
            app(RecordQuestEvent::class)->handle($run, QuestEventType::DefinitionEdited, payload: ['by' => 'creator'], byCreator: true);
            $this->broadcast($run);
        }

        return ['status' => 'edited', 'warnings' => $result['warnings']];
    }
}
