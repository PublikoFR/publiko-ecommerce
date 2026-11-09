<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Support;

/**
 * Remplacement des anciens liens « devis » par défaut (avant le formulaire
 * unique) dans les réglages storefront surchargés en base.
 *
 * Seules les anciennes valeurs PAR DÉFAUT sont remplacées :
 * - `/pages/identifier-une-piece` (page jamais créée) → mode photo ;
 * - `/contact`, uniquement sur un lien dont le libellé parle de devis.
 *   Un lien « Nous contacter » vers /contact reste intact : la page contact
 *   existe toujours.
 * Une URL personnalisée par l'admin n'est jamais touchée.
 */
final class LegacyQuoteLinks
{
    public const OLD_CONTACT = '/contact';

    public const OLD_PART = '/pages/identifier-une-piece';

    public static function navQuoteUrl(mixed $value): mixed
    {
        return $value === self::OLD_CONTACT ? QuoteRequestUrl::PATH : $value;
    }

    /**
     * @param  array<int, mixed>  $links  liens `{label, href}` (colonne de footer)
     * @return array<int, mixed>
     */
    public static function links(array $links, string $labelKey = 'label'): array
    {
        return array_map(function (mixed $link) use ($labelKey): mixed {
            if (! is_array($link) || ! isset($link['href'])) {
                return $link;
            }

            $label = mb_strtolower((string) ($link[$labelKey] ?? ''));

            if ($link['href'] === self::OLD_PART) {
                $link['href'] = QuoteRequestUrl::PATH.'?mode=part_photo';
            } elseif ($link['href'] === self::OLD_CONTACT && str_contains($label, 'devis')) {
                $link['href'] = str_contains($label, 'projet')
                    ? QuoteRequestUrl::PATH.'?mode=project'
                    : QuoteRequestUrl::PATH;
            }

            return $link;
        }, $links);
    }

    /**
     * @param  array<int, mixed>  $columns  colonnes `{title, links: [...]}`
     * @return array<int, mixed>
     */
    public static function footerColumns(array $columns): array
    {
        return array_map(function (mixed $column): mixed {
            if (is_array($column) && is_array($column['links'] ?? null)) {
                $column['links'] = self::links($column['links']);
            }

            return $column;
        }, $columns);
    }
}
