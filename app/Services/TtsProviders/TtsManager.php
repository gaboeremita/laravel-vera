<?php

namespace App\Services\TtsProviders;

use App\Contracts\TtsProvider;
use App\Enums\VoiceProviderFormat;
use App\Models\AssistantUser;
use App\Models\Settings;
use App\Models\VoiceModel;
use App\Models\VoiceProvider;

class TtsManager
{
	public function forAssistantUser(AssistantUser $assistantUser): TtsProvider
	{
		$voiceModel = $this->resolveVoiceModel($assistantUser);

		return $voiceModel ? $this->fromModel($voiceModel) : $this->fromConfig();
	}

	/**
	 * The DB-backed voice model selected for this assistant+user pair, if any.
	 * Null means the caller is falling back to config('ai.tts') — no DB row to source a provider/model prompt from.
	 */
	public function resolveVoiceModel(AssistantUser $assistantUser): ?VoiceModel
	{
		$settings = Settings::where('user_id', $assistantUser->user_id)
			->where('assistant_id', $assistantUser->assistant_id)
			->first();

		$selectedModelId = $settings?->data['tts_model_id'] ?? null;

		return $selectedModelId
			? VoiceModel::with('provider')->findOrFail($selectedModelId)
			: null;
	}

	public function fromModel(VoiceModel $voiceModel): TtsProvider
	{
		$class = $voiceModel->provider->format->providerClass();

		return $class::fromModel($voiceModel);
	}

	public function fromConfig(): TtsProvider
	{
		$config = config('ai.tts');

		if (! $config || ! $config['url']) {
			throw new \InvalidArgumentException('No default TTS provider configured.');
		}

		$format = VoiceProviderFormat::from($config['format'] ?? 'openai_compatible');
		$class = $format->providerClass();

		$voiceProvider = new VoiceProvider([
			'url' => $config['url'],
			'api_key' => $config['key'] ?? null,
			'format' => $format,
		]);

		$voiceModel = new VoiceModel([
			'name' => $config['model'],
			'endpoint' => $config['model'],
			'voices' => $config['voice'] ? [$config['voice']] : [],
			'config' => ['timeout' => $config['timeout'] ?? 120],
		]);
		$voiceModel->setRelation('provider', $voiceProvider);

		return $class::fromModel($voiceModel);
	}
}
