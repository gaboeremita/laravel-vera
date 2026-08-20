<?php

namespace App\Services\ImageGenProviders;

use App\Contracts\ImageGenProvider as ImageGenProviderContract;
use App\Enums\ImageGenProviderFormat;
use App\Models\AssistantUser;
use App\Models\ImageGenModel;
use App\Models\ImageGenProvider;
use App\Models\Settings;

class ImageGenManager
{
    public function forAssistantUser(AssistantUser $assistantUser): ImageGenProviderContract
    {
        $imageGenModel = $this->resolveImageGenModel($assistantUser);

        return $imageGenModel ? $this->fromModel($imageGenModel) : $this->fromConfig();
    }

    public function resolveImageGenModel(AssistantUser $assistantUser): ?ImageGenModel
    {
        $settings = Settings::where('user_id', $assistantUser->user_id)
            ->where('assistant_id', $assistantUser->assistant_id)
            ->first();

        return $selectedModelId
            ? ImageGenModel::with('provider')
                ->whereKey($selectedModelId)
                ->whereHas('provider', fn ($q) => $q->where('user_id', $assistantUser->user_id))
                ->first()
            : null;
    }

    public function fromModel(ImageGenModel $imageGenModel): ImageGenProviderContract
    {
        $class = $imageGenModel->provider->format->providerClass();

        return $class::fromModel($imageGenModel);
    }

    public function fromConfig(): ImageGenProviderContract
    {
        $config = config('ai.image_gen');

        if (! $config || ! $config['url']) {
            throw new \InvalidArgumentException('No default image generation provider configured.');
        }

        $format = ImageGenProviderFormat::from($config['format'] ?? 'openrouter');
        $class = $format->providerClass();

        $provider = new ImageGenProvider([
            'url' => $config['url'],
            'api_key' => $config['key'] ?? null,
            'format' => $format,
        ]);

        $model = new ImageGenModel([
            'name' => $config['model'],
            'endpoint' => $config['model'],
            'config' => ['timeout' => $config['timeout'] ?? 120],
        ]);
        $model->setRelation('provider', $provider);

        return $class::fromModel($model);
    }
}
