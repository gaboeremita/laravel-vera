<?php

namespace App\Services\LlmProviders;

use App\Contracts\LlmProvider;
use App\Enums\AiProviderFormat;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AssistantUser;

class LlmManager
{
	public function forAssistantUser(AssistantUser $assistantUser): LlmProvider
	{
		$selectedModelId = $assistantUser->settings?->data['ai_model_id'] ?? null;

		if ($selectedModelId) {
			$aiModel = AiModel::with('provider')->findOrFail($selectedModelId);
			return $this->fromModel($aiModel);
		}

		return $this->fromConfig();
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
			'thinking' => $config['thinking'] ?? false,
			'config' => $config['config'] ?? [],
		]);
		$aiModel->setRelation('provider', $aiProvider);

		return $class::fromModel($aiModel);
	}
}