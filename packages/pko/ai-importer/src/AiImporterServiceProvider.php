<?php

declare(strict_types=1);

namespace Pko\AiImporter;

use Illuminate\Support\ServiceProvider;
use Pko\AiImporter\Actions\ActionRegistry;
use Pko\AiImporter\Console\ImportPsConfigCommand;
use Pko\AiImporter\Console\PreviewConfigCommand;
use Pko\AiImporter\Console\RunScheduledImportsCommand;
use Pko\AiImporter\Services\ActionPipeline;

class AiImporterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-importer.php', 'ai-importer');

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
                PreviewConfigCommand::class,
                RunScheduledImportsCommand::class,
            ]);
        }
    }
}
