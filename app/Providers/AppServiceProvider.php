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

		$this->app->bind(EmbeddingProvider::class, function () {
			return new OllamaEmbeddingProvider(
				baseUrl: config('ai.embedding.url'),
				model: config('ai.embedding.model'),
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
