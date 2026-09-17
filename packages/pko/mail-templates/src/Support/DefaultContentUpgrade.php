<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\PageBuilder\Services\PageBuilderManager;

/**
 * Applique au déploiement une nouvelle version du contenu par défaut d'un e-mail.
 *
 * `pko:mail-templates:sync` ne touche pas une ligne déjà en base : sans ce
 * mécanisme, un texte par défaut revu dans `database/content/fr.php` n'arrive
 * jamais en production. Appelé depuis une migration, il remplace le contenu
 * **seulement s'il est resté identique à l'ancienne version par défaut** :
 * un texte retouché en back-office n'est jamais écrasé.
 *
 * La comparaison se fait sur l'arbre normalisé, identifiants retirés :
 * l'éditeur back-office enregistre des identifiants de sections et de blocs et
 * des valeurs par défaut (marges, couleurs) que la synchro n'écrit pas. Un
 * modèle ouvert puis réenregistré sans changement reste donc « non retouché ».
 */
final class DefaultContentUpgrade
{
    public const UPDATED = 'updated';

    public const CURRENT = 'current';

    public const CUSTOMIZED = 'customized';

    public const MISSING = 'missing';

    /**
     * @param  array{subject: string, content: array<string, mixed>}  $previous  Version par défaut remplacée.
     */
    public static function apply(string $key, array $previous, string $locale = 'fr'): string
    {
        if (! Schema::hasTable('pko_mail_templates')) {
            return self::MISSING;
        }

        $template = MailTemplate::query()->where('key', $key)->where('locale', $locale)->first();
        $next = TemplateResolver::defaults($locale)[$key] ?? null;

        // Pas de ligne : la synchro la créera directement avec le nouveau contenu.
        if ($template === null || $next === null) {
            return self::MISSING;
        }

        if ($template->subject === $next['subject'] && self::same($template->content, $next['content'])) {
            return self::CURRENT;
        }

        if ($template->subject !== $previous['subject'] || ! self::same($template->content, $previous['content'])) {
            Log::warning('Mail template kept: customized in back-office', ['key' => $key, 'locale' => $locale]);

            return self::CUSTOMIZED;
        }

        // `enabled` n'est pas repris : une désactivation en back-office est un choix, pas un contenu.
        $template->update([
            'subject' => $next['subject'],
            'content' => $next['content'],
        ]);

        return self::UPDATED;
    }

    /**
     * Charge un fichier de `database/content/upgrades/` et l'applique.
     *
     * @return array<string, string> Résultat par clé.
     */
    public static function applyFile(string $path): array
    {
        /** @var array<string, array{subject: string, content: array<string, mixed>}> $previousByKey */
        $previousByKey = require $path;
        $results = [];

        foreach ($previousByKey as $key => $previous) {
            $results[$key] = self::apply($key, $previous);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>|null  $a
     * @param  array<string, mixed>|null  $b
     */
    private static function same(?array $a, ?array $b): bool
    {
        return self::canonical($a) === self::canonical($b);
    }

    /** @param array<string, mixed>|null $content */
    private static function canonical(?array $content): string
    {
        $tree = PageBuilderManager::normalize($content);

        foreach ($tree['sections'] as &$section) {
            unset($section['id']);

            foreach ($section['columns'] as &$column) {
                foreach ($column['blocks'] as &$block) {
                    unset($block['id']);
                }
            }
        }

        return (string) json_encode($tree);
    }
}
