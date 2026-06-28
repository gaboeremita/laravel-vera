<?php

namespace App\Providers;

use App\Contracts\EmbeddingProvider;
use App\Contracts\LlmProvider;
use App\Providers\Embeddings\OllamaEmbeddingProvider;
use App\Services\LlmProviders\LlmManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
		$this->app->bind(LlmProvider::class, function () {
			return (new LlmManager())->resolve();
		});

		$this->app->bind(EmbeddingProvider::class, function () {
			return new OllamaEmbeddingProvider(
				baseUrl: config('ai.providers.ollama.url'),
				model: config('ai.providers.ollama.embedding_model'),
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
