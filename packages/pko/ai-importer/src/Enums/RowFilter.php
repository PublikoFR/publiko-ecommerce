<?php

declare(strict_types=1);

namespace Pko\AiImporter\Enums;

/**
 * Filtre d'écriture selon la présence du produit en base (option de job
 * `row_filter`). La présence est déterminée via la colonne de jointure
 * (`join_column`) au moment de l'écriture Lunar :
 *
 *  - `all`                  : tout écrire (création + mise à jour).
 *  - `missing_supplier_ref` : n'écrire que les produits absents (créations) —
 *                             les produits déjà connus sont marqués « ignoré ».
 *  - `existing_supplier_ref`: n'écrire que les produits déjà connus (mises à
 *                             jour) — les inconnus sont marqués « ignoré ».
 */
enum RowFilter: string
{
    case All = 'all';
    case MissingSupplierRef = 'missing_supplier_ref';
    case ExistingSupplierRef = 'existing_supplier_ref';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Toutes les lignes',
            self::MissingSupplierRef => 'Produits absents uniquement (création)',
            self::ExistingSupplierRef => 'Produits existants uniquement (mise à jour)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::All => 'gray',
            self::MissingSupplierRef => 'success',
            self::ExistingSupplierRef => 'info',
        };
    }

    /**
     * Faut-il écrire ce record sachant qu'il existe (ou non) en base ?
     */
    public function allows(bool $exists): bool
    {
        return match ($this) {
            self::All => true,
            self::MissingSupplierRef => ! $exists,
            self::ExistingSupplierRef => $exists,
        };
    }
}
