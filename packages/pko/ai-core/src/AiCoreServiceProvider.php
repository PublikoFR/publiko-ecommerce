<?php

declare(strict_types=1);

namespace Pko\AiCore;

use Illuminate\Support\ServiceProvider;
use Pko\AiCore\Llm\LlmManager;

class AiCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-core.php', 'ai-core');

        $this->app->singleton(LlmManager::class, fn () => new LlmManager);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/ai-core.php' => config_path('ai-core.php'),
        ], 'ai-core-config');
    }
}
