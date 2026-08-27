<?php

namespace App\Services\LlmProviders;

use App\Contracts\LlmProvider;
use App\Enums\AiProviderFormat;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AssistantUser;
use App\Models\Settings;

class LlmManager
{
    public function forAssistantUser(AssistantUser $assistantUser): LlmProvider
    {
        $aiModel = $this->resolveModelForAssistantUser($assistantUser);

        return $aiModel ? $this->fromModel($aiModel) : $this->fromConfig();
    }

    /**
     * Returns the assistant's explicitly selected `AiModel`, or null when it falls
     * back to the config-defined default (which has no `supports_tools` flag to check).
     */
    public function resolveModelForAssistantUser(AssistantUser $assistantUser): ?AiModel
    {
        $settings = Settings::where('user_id', $assistantUser->user_id)
            ->where('assistant_id', $assistantUser->assistant_id)
            ->first();

        $selectedModelId = $settings?->data['ai_model_id'] ?? null;

        return $selectedModelId ? AiModel::with('provider')->findOrFail($selectedModelId) : null;
    }

    public function fromModel(AiModel $aiModel): LlmProvider
    {
        $class = $aiModel->provider->format->providerClass();

        return $class::fromModel($aiModel);
    }

    public function fromConfig(): LlmProvider
    {
        $config = config('ai.default');

        if (! $config || ! $config['url']) {
            throw new \InvalidArgumentException('No default LLM provider configured.');
        }

        $format = AiProviderFormat::from($config['format'] ?? 'generic');
        $class = $format->providerClass();

        $aiProvider = new AiProvider([
            'url' => $config['url'],
            'api_key' => $config['key'] ?? '',
            'format' => $format,
            'config_schema' => [
                'thinking_key' => $config['config']['thinking_key'] ?? null,
            ],
        ]);

        $aiModel = new AiModel([
            'name' => $config['model'],
            'endpoint' => $config['model'],
            'thinking' => $config['thinking'] ?? false,
            'config' => $config['config'] ?? [],
        ]);
        $aiModel->setRelation('provider', $aiProvider);

        return $class::fromModel($aiModel);
    }
}
