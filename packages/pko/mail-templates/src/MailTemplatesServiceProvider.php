<?php

declare(strict_types=1);

namespace Pko\MailTemplates;

use Illuminate\Support\ServiceProvider;
use Pko\MailTemplates\Console\SyncMailTemplatesCommand;
use Pko\MailTemplates\Support\BrandMailFrom;

class MailTemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pko-mail-templates');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'pko-mail-templates');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/pko-mail-templates'),
        ], 'pko-mail-templates-lang');

        // Expéditeur aligné sur l'identité de la boutique (§3.0) : l'adresse et le
        // nom viennent du Setting storefront, pas d'un .env figé au déploiement.
        BrandMailFrom::apply();

        // Prévisualisation des mails, strictement hors production.
        if ($this->app->environment('local', 'testing')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SyncMailTemplatesCommand::class]);
        }
    }
}
