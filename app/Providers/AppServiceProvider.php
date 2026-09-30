<?php

namespace App\Providers;

use App\Actions\Activities\Effects\GiveCredits;
use App\Actions\Activities\Effects\GiveItems;
use App\Actions\Activities\Effects\MakePassable;
use App\Actions\Activities\Effects\RevealFact;
use App\Actions\Activities\Effects\ShowText;
use App\Actions\Activities\Effects\TakeCredits;
use App\Actions\Activities\Effects\TakeItems;
use App\Contracts\EmbeddingProvider;
use App\Contracts\SttProvider;
use App\Providers\Embeddings\OllamaEmbeddingProvider;
use App\Providers\Stt\WhisperSttProvider;
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

        $this->app->tag([
            GiveItems::class,
            TakeItems::class,
            GiveCredits::class,
            TakeCredits::class,
            ShowText::class,
            RevealFact::class,
            MakePassable::class,
        ], 'activityEffects');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
