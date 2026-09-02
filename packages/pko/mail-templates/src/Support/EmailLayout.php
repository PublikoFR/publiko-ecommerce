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

    public static function columnWidth(int $columns): int
    {
        if ($columns <= 1 || $columns > self::MAX_SIDE_BY_SIDE) {
            return self::CONTENT_WIDTH;
        }

        $gaps = self::COLUMN_GAP * ($columns - 1);

        return (int) floor((self::CONTENT_WIDTH - $gaps) / $columns);
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
