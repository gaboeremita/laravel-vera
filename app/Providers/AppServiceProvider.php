<?php

namespace App\Providers;

use App\Contracts\EmbeddingProvider;
use App\Contracts\LlmProvider;
use App\Contracts\SttProvider;
use App\Contracts\TtsProvider;
use App\Providers\Embeddings\OllamaEmbeddingProvider;
use App\Providers\Stt\WhisperSttProvider;
use App\Providers\Tts\OrpheusTtsProvider;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

		$this->app->bind(EmbeddingProvider::class, function () {
			return new OllamaEmbeddingProvider(
				baseUrl: config('ai.embedding.url'),
				model: config('ai.embedding.model'),
			);
		});

		$this->app->bind(SttProvider::class, function () {
			return new WhisperSttProvider(
				baseUrl: config('ai.stt.url'),
				model: config('ai.stt.model'),
				timeout: config('ai.stt.timeout'),
			);
		});

		$this->app->bind(TtsProvider::class, function () {
			return new OrpheusTtsProvider(
				url: config('ai.tts.url'),
				model: config('ai.tts.model'),
				defaultVoice: config('ai.tts.voice'),
				timeout: config('ai.tts.timeout'),
			);
		});
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
