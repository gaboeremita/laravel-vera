<?php

namespace App\Services\VideoGenProviders;

use App\Contracts\VideoGenProvider as VideoGenProviderContract;
use App\Enums\VideoGenProviderFormat;
use App\Models\AssistantUser;
use App\Models\Settings;
use App\Models\VideoGenModel;
use App\Models\VideoGenProvider;

class VideoGenManager
{
    public function forAssistantUser(AssistantUser $assistantUser): VideoGenProviderContract
    {
        $videoGenModel = $this->resolveVideoGenModel($assistantUser);

        return $videoGenModel ? $this->fromModel($videoGenModel) : $this->fromConfig();
    }

    public function resolveVideoGenModel(AssistantUser $assistantUser): ?VideoGenModel
    {
        $settings = Settings::where('user_id', $assistantUser->user_id)
            ->where('assistant_id', $assistantUser->assistant_id)
            ->first();

        $selectedModelId = $settings?->data['video_gen_model_id'] ?? null;

        return $selectedModelId
            ? VideoGenModel::with('provider')
                ->whereKey($selectedModelId)
                ->whereHas('provider', fn ($q) => $q->where('user_id', $assistantUser->user_id))
                ->first()
            : null;
    }

    public function fromModel(VideoGenModel $videoGenModel): VideoGenProviderContract
    {
        $class = $videoGenModel->provider->format->providerClass();

        return $class::fromModel($videoGenModel);
    }

    public function fromConfig(): VideoGenProviderContract
    {
        return $this->fromModel($this->configuredModel());
    }

    /**
     * The fallback model from config/ai.php, built in memory for when an assistant has none selected.
     *
     * @throws \InvalidArgumentException when no fallback model is configured
     */
    public function configuredModel(): VideoGenModel
    {
        $config = config('ai.video_gen');

        if (! $config || empty($config['url']) || empty($config['model'])) {
            throw new \InvalidArgumentException('No default video generation provider configured.');
        }

        $provider = new VideoGenProvider([
            'url' => $config['url'],
            'api_key' => $config['key'] ?? null,
            'format' => VideoGenProviderFormat::from($config['format'] ?? 'openrouter'),
        ]);

        $model = new VideoGenModel([
            'name' => $config['model'],
            'endpoint' => $config['model'],
            'config' => ['timeout' => $config['timeout'] ?? 600],
        ]);
        $model->setRelation('provider', $provider);

        return $model;
    }
}
