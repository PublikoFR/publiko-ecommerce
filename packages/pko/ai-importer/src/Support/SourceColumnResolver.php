<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Pko\AiImporter\Actions\ExecutionContext;

/**
 * Résout une référence de colonne source d'une config PrestaShop
 * (`source_columns` d'une action IA) vers le texte de la ligne courante.
 *
 * Formes acceptées :
 *  - `Feuille:Col`                    → valeur de la colonne (lettre ou intitulé) ;
 *  - `Feuille:[ColFiltre=VALEUR,Col]` ou `Feuille[ColFiltre=VALEUR,Col]`
 *                                     → valeurs de `Col` sur les lignes de la
 *                                       feuille dont `ColFiltre` vaut VALEUR.
 *
 * Feuille secondaire : toutes les lignes jointes à la ligne courante (relation
 * « many » incluse). Plusieurs valeurs sont réunies, séparées par une ligne vide.
 */
final class SourceColumnResolver
{
    private const PATTERN = '/^([^:\[\]]+)\s*(?::\s*)?(?:\[\s*([^=,\]]+?)\s*=\s*([^,\]]*?)\s*,\s*([^\]]+?)\s*\]|([^:\[\]]+?))\s*$/u';

    public static function resolve(string $spec, ExecutionContext $ctx): ?string
    {
        if (! preg_match(self::PATTERN, trim($spec), $m)) {
            return null;
        }

        $sheet = trim($m[1]);
        $filterCol = ($m[2] ?? '') !== '' ? $m[2] : null;
        $filterVal = $m[3] ?? '';
        $outCol = $filterCol !== null ? $m[4] : trim($m[5] ?? '');

        $config = $ctx->job->config?->config_data;
        $primary = $config instanceof \ArrayObject ? ($config->getArrayCopy()['primary_sheet'] ?? null) : null;

        $rows = $sheet === $primary ? [$ctx->row] : ($ctx->sheets[$sheet] ?? []);

        $values = [];
        foreach ($rows as $row) {
            if ($filterCol !== null && self::normalize(self::cell($row, $filterCol)) !== self::normalize($filterVal)) {
                continue;
            }
            $value = self::cell($row, $outCol);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values === [] ? null : implode("\n\n", array_unique($values));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function cell(array $row, string $col): string
    {
        $col = trim($col);
        if (! array_key_exists($col, $row)) {
            foreach ($row as $key => $_) {
                if (self::normalize((string) $key) === self::normalize($col)) {
                    $col = (string) $key;
                    break;
                }
            }
        }
        $value = $row[$col] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
