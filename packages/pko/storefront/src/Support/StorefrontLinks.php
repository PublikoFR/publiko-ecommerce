<?php

declare(strict_types=1);

namespace Pko\Storefront\Support;

/**
 * Normalisation des listes de liens éditoriaux du storefront (menu, bandeau
 * supérieur, footer, raccourcis de la home) saisies en back-office.
 *
 * Deux règles, appliquées partout au même endroit :
 *
 * 1. Un lien sans libellé ou sans URL est masqué. Le menu de la maquette
 *    pointe vers des pages qui n'existent pas encore (configurateur,
 *    exclusivités…) : vider l'URL en admin suffit à retirer l'entrée sans
 *    perdre son libellé.
 * 2. Seules les URL sûres sont rendues : chemin relatif (`/…`, `#…`),
 *    `http(s)://`, `mailto:` et `tel:`. Un `javascript:` saisi en admin
 *    finirait sinon dans un `href` exécuté au clic.
 */
final class StorefrontLinks
{
    /**
     * @param  mixed  $links  liste brute issue de config('storefront.*')
     * @return list<array<string, mixed>> entrées valides, `href` nettoyé
     */
    public static function visible(mixed $links, string $labelKey = 'label'): array
    {
        if (! is_array($links)) {
            return [];
        }

        $out = [];
        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            $label = trim((string) ($link[$labelKey] ?? ''));
            $href = self::safeHref($link['href'] ?? null);

            if ($label === '' || $href === null) {
                continue;
            }

            $out[] = array_merge($link, [$labelKey => $label, 'href' => $href]);
        }

        return $out;
    }

    public static function safeHref(mixed $href): ?string
    {
        if (! is_string($href)) {
            return null;
        }

        $href = trim($href);
        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, '//')) {
            return null;
        }

        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return $href;
        }

        return preg_match('#^(https?://|mailto:|tel:)#i', $href) === 1 ? $href : null;
    }

    /**
     * Lien interne : navigation Livewire SPA (`wire:navigate`) possible.
     */
    public static function isInternal(string $href): bool
    {
        return str_starts_with($href, '/') && ! str_starts_with($href, '//');
    }
}
