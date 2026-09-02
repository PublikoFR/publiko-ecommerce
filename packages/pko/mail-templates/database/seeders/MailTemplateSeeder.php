<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Database\Seeders;

use Illuminate\Database\Seeder;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\TemplateResolver;

/**
 * Écrit les contenus par défaut en base.
 *
 * Idempotent et non destructif : une ligne déjà présente n'est PAS écrasée.
 * Rejouer `db:seed` après que le client a retouché ses textes en back-office
 * ne doit jamais lui reprendre son travail. Pour forcer la réécriture :
 * `php artisan pko:mail-templates:sync --force`.
 */
class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TemplateResolver::defaults('fr') as $key => $content) {
            MailTemplate::query()->firstOrCreate(
                ['key' => $key, 'locale' => 'fr'],
                [
                    'subject' => $content['subject'],
                    'content' => $content['content'],
                    'enabled' => $content['enabled'] ?? true,
                ],
            );
        }
    }
}
