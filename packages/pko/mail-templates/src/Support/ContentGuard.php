<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

/**
 * Contrôle de cohérence d'un contenu d'e-mail avant enregistrement.
 *
 * Deux fautes possibles quand on réécrit un texte depuis le back-office :
 * supprimer une variable dont l'e-mail a besoin (un lien de suivi, une
 * référence de commande — le message part, mais vide de sa fonction), ou en
 * inventer une (elle ne sera jamais remplacée et s'affichera telle quelle).
 */
final class ContentGuard
{
    /**
     * @param  array{heading?: string, sections?: array<int, array<string, mixed>>}  $content
     * @return string|null Message d'erreur, ou null si le contenu est valide.
     */
    public static function check(string $key, string $subject, array $content, bool $enabled): ?string
    {
        if (! MailTemplateRegistry::has($key)) {
            return null;
        }

        $meta = MailTemplateRegistry::get($key);
        $used = Placeholders::found($subject, $content);

        $unknown = array_diff($used, $meta['placeholders']);

        if ($unknown !== []) {
            return __('pko-mail-templates::admin.error.unknown_placeholder', [
                'list' => self::format($unknown),
            ]);
        }

        // Un modèle désactivé a le droit d'être incomplet : c'est l'état des
        // e-mails dont le contenu n'a pas encore été fourni par le client.
        if (! $enabled) {
            return null;
        }

        $missing = array_diff($meta['required'], $used);

        if ($missing !== []) {
            return __('pko-mail-templates::admin.error.missing_required', [
                'list' => self::format($missing),
            ]);
        }

        return null;
    }

    /** @param array<int|string, string> $placeholders */
    private static function format(array $placeholders): string
    {
        return implode(', ', array_map(static fn (string $p): string => ':'.$p, $placeholders));
    }
}
