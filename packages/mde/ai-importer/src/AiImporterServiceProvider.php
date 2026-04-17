<?php

declare(strict_types=1);

namespace Mde\AiImporter;

use Illuminate\Support\ServiceProvider;
use Mde\AiImporter\Actions\ActionRegistry;
use Mde\AiImporter\Console\ImportPsConfigCommand;
use Mde\AiImporter\Llm\LlmManager;
use Mde\AiImporter\Services\ActionPipeline;

class AiImporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-importer.php', 'ai-importer');

        $this->app->singleton(LlmManager::class, fn () => new LlmManager);
        $this->app->singleton(ActionPipeline::class, fn () => new ActionPipeline);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/ai-importer.php' => config_path('ai-importer.php'),
        ], 'ai-importer-config');

        ActionRegistry::bootDefaults();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportPsConfigCommand::class,
            ]);
        }
    }
}
