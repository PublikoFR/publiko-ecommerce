<?php

declare(strict_types=1);

namespace Pko\AiImporter;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Pko\AiImporter\Actions\ActionRegistry;
use Pko\AiImporter\Console\ImportPsConfigCommand;
use Pko\AiImporter\Console\PreviewConfigCommand;
use Pko\AiImporter\Console\RunScheduledImportsCommand;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages\CreateImporterConfig;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages\EditImporterConfig;
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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pko-ai-importer');

        $this->publishes([
            __DIR__.'/../config/ai-importer.php' => config_path('ai-importer.php'),
        ], 'ai-importer-config');

        ActionRegistry::bootDefaults();

        // Éditeur de pipeline custom (modal « Configurer ») : on injecte le moteur
        // JS lifté + le CSS + les globals (palette, opérateurs…) dans le HTML
        // initial des pages Create/Edit d'une configuration. Indispensable de le
        // faire au niveau page : les <script> inline d'un contenu de modal chargé
        // par Livewire ne s'exécutent pas de façon fiable.
        FilamentView::registerRenderHook(
            // BODY_END (et non PAGE_END) : on injecte hors du composant Livewire de
            // la page, sinon les balises <style>/<script> ajoutent des éléments
            // racine et Livewire lève MultipleRootElementsDetectedException.
            PanelsRenderHook::BODY_END,
            static fn (): string => view('pko-ai-importer::filament.pipeline-editor-assets', [
                // Chemins absolus résolus ici (où __DIR__ est fiable) : dans une vue
                // Blade compilée, __DIR__ pointe vers le cache, pas vers la source.
                'cssPath' => __DIR__.'/../resources/css/pipeline-editor.css',
                'jsPath' => __DIR__.'/../resources/js/pipeline-editor.js',
            ])->render(),
            scopes: [
                CreateImporterConfig::class,
                EditImporterConfig::class,
            ],
        );

        // Enregistrées inconditionnellement : la page Filament CreateImportJob
        // appelle ai-importer:import-ps-config via Artisan::call() en contexte
        // HTTP, où runningInConsole() est false.
        $this->commands([
            ImportPsConfigCommand::class,
            PreviewConfigCommand::class,
            RunScheduledImportsCommand::class,
        ]);
    }
}
