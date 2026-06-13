<?php

namespace App\Providers;

use App\Contracts\LlmProvider;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
