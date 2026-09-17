<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Console;

use Illuminate\Console\Command;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\MailTemplateRegistry;
use Pko\MailTemplates\Support\TemplateResolver;

class SyncMailTemplatesCommand extends Command
{
    protected $signature = 'pko:mail-templates:sync
                            {--force : Écrase les contenus déjà présents en base}
                            {--key=* : Limite la synchronisation à ces clés (ex. --key=loyalty.tier_unlocked)}
                            {--locale=fr : Langue à synchroniser}';

    protected $description = 'Synchronise les contenus par défaut des e-mails vers la base.';

    public function handle(): int
    {
        $locale = (string) $this->option('locale');
        $force = (bool) $this->option('force');
        $created = 0;
        $updated = 0;
        $only = array_filter((array) $this->option('key'));

        foreach (TemplateResolver::defaults($locale) as $key => $content) {
            // `--force --key=…` : réapplique un contenu par défaut revu sans
            // écraser les retouches faites en back-office sur les autres e-mails.
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            if (! MailTemplateRegistry::has($key)) {
                $this->warn("Clé absente du registre, ignorée : {$key}");

                continue;
            }

            $attributes = [
                'subject' => $content['subject'],
                'content' => $content['content'],
                'enabled' => $content['enabled'] ?? true,
            ];

            $existing = MailTemplate::query()
                ->where('key', $key)
                ->where('locale', $locale)
                ->first();

            if ($existing === null) {
                MailTemplate::query()->create($attributes + ['key' => $key, 'locale' => $locale]);
                $created++;

                continue;
            }

            // Sans --force, on préserve toute retouche faite en back-office.
            if ($force) {
                $existing->update($attributes);
                $updated++;
            }
        }

        $this->info("E-mails synchronisés — {$created} créé(s), {$updated} mis à jour.");

        // Un contenu manquant pour une clé déclarée est une erreur de livraison,
        // pas un détail : le mail correspondant ne partirait jamais.
        $missing = array_diff(MailTemplateRegistry::keys(), array_keys(TemplateResolver::defaults($locale)));

        if ($missing !== []) {
            $this->error('Clés déclarées sans contenu : '.implode(', ', $missing));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
