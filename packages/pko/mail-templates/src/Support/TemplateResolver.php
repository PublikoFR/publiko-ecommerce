<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

use Illuminate\Support\Facades\Schema;
use Pko\MailTemplates\Models\MailTemplate;

/**
 * Résout le contenu d'un e-mail : base de données d'abord (édité par le client),
 * fichier de contenu par défaut ensuite.
 *
 * Le fallback n'est pas un filet de sécurité théorique : il garantit qu'un
 * déploiement dont le seeder n'a pas encore tourné, ou une clé ajoutée par une
 * nouvelle version du code, envoie quand même un mail correct.
 */
final class TemplateResolver
{
    /** @var array<string, array{subject: string, blocks: array<int, array<string, mixed>>, enabled: bool}>|null */
    private static ?array $defaults = null;

    /**
     * @return array{subject: string, blocks: array<int, array<string, mixed>>, enabled: bool}|null
     *                                                                                              null si la clé est inconnue ou le mail désactivé.
     */
    public static function resolve(string $key, string $locale = 'fr'): ?array
    {
        $record = self::fromDatabase($key, $locale);

        if ($record !== null) {
            return $record['enabled'] ? $record : null;
        }

        $default = self::defaults($locale)[$key] ?? null;

        if ($default === null) {
            return null;
        }

        return ($default['enabled'] ?? true) ? $default : null;
    }

    /**
     * @return array{subject: string, blocks: array<int, array<string, mixed>>, enabled: bool}|null
     */
    private static function fromDatabase(string $key, string $locale): ?array
    {
        // La table peut ne pas exister : pendant `migrate:fresh`, dans un test qui
        // ne charge pas les migrations du package, ou sur un déploiement en cours.
        // On retombe alors silencieusement sur les contenus par défaut.
        if (! Schema::hasTable('pko_mail_templates')) {
            return null;
        }

        $template = MailTemplate::query()
            ->where('key', $key)
            ->where('locale', $locale)
            ->first();

        if ($template === null) {
            return null;
        }

        return [
            'subject' => $template->subject,
            'blocks' => $template->blocks,
            'enabled' => $template->enabled,
        ];
    }

    /**
     * @return array<string, array{subject: string, blocks: array<int, array<string, mixed>>, enabled: bool}>
     */
    public static function defaults(string $locale = 'fr'): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }

        $path = __DIR__.'/../../database/content/'.$locale.'.php';

        if (! is_file($path)) {
            $path = __DIR__.'/../../database/content/fr.php';
        }

        /** @var array<string, array{subject: string, blocks: array<int, array<string, mixed>>, enabled: bool}> $content */
        $content = require $path;

        return self::$defaults = $content;
    }

    /** Vide le cache mémoire — utile en test. */
    public static function flush(): void
    {
        self::$defaults = null;
    }
}
