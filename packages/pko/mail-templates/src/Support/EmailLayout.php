<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

/**
 * Géométrie des colonnes d'un e-mail.
 *
 * Les largeurs sont calculées en pixels et non en pourcentages : c'est la
 * `max-width` en pixels qui déclenche le repli des colonnes sur petit écran,
 * un pourcentage resterait proportionnel et n'empilerait jamais rien.
 */
final class EmailLayout
{
    /** Largeur du gabarit e-mail (cf. storefront-cms::components.mail.layout). */
    public const CANVAS_WIDTH = 600;

    /** Padding horizontal du corps, de chaque côté. */
    public const CANVAS_PADDING = 32;

    /** Gouttière entre deux colonnes. */
    public const COLUMN_GAP = 16;

    /** Largeur réellement disponible pour le contenu. */
    public const CONTENT_WIDTH = self::CANVAS_WIDTH - (2 * self::CANVAS_PADDING);

    /**
     * Au-delà de 3 colonnes, un e-mail devient illisible même sur desktop :
     * on rend alors chaque colonne pleine largeur, donc empilée.
     */
    public const MAX_SIDE_BY_SIDE = 3;

    /**
     * @param  int  $available  Largeur disponible : réduite du padding horizontal
     *                          quand la section est encadrée (fond, marge interne).
     */
    public static function columnWidth(int $columns, int $available = self::CONTENT_WIDTH): int
    {
        if ($columns <= 1 || $columns > self::MAX_SIDE_BY_SIDE) {
            return $available;
        }

        $gaps = self::COLUMN_GAP * ($columns - 1);

        return (int) floor(($available - $gaps) / $columns);
    }

    /**
     * Un bloc qui ne rendrait rien (image sans source, texte ou titre vides une
     * fois les variables remplacées) ne doit pas occuper de place : sans ce
     * filtre, une colonne ne contenant qu'une image absente resterait vide à
     * côté du texte au lieu de lui laisser toute la largeur.
     *
     * @param  array<string, mixed>  $block
     */
    public static function blockIsEmpty(array $block): bool
    {
        return match ($block['type'] ?? '') {
            'image' => empty($block['media_id']) && trim((string) ($block['url'] ?? '')) === '',
            'text' => trim(strip_tags((string) ($block['html'] ?? ''), '<img>')) === '',
            'title', 'callout' => trim((string) ($block['text'] ?? '')) === '',
            default => false,
        };
    }

    /** Largeur en pourcentage pour la table de repli Outlook. */
    public static function msoColumnPercent(int $columns): int
    {
        if ($columns <= 1 || $columns > self::MAX_SIDE_BY_SIDE) {
            return 100;
        }

        return (int) floor(100 / $columns);
    }
}
