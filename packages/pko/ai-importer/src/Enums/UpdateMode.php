<?php

declare(strict_types=1);

namespace Pko\AiImporter\Enums;

/**
 * Stratégie de mise à jour d'un produit *déjà présent* en base (option de job
 * `update_mode`). Sans effet à la création : un produit neuf est toujours créé
 * intégralement. Portage du « Si le produit existe déjà » du module PrestaShop
 * (`all` / `price_only` / `stock_only` / `price_and_stock`).
 */
enum UpdateMode: string
{
    case All = 'all';
    case Price = 'price';
    case Stock = 'stock';
    case PriceStock = 'price_stock';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tout mettre à jour',
            self::Price => 'Prix uniquement',
            self::Stock => 'Stock uniquement',
            self::PriceStock => 'Prix et stock',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::All => 'info',
            self::Price => 'warning',
            self::Stock => 'success',
            self::PriceStock => 'primary',
        };
    }

    /** Le prix doit-il être écrit sur un produit existant dans ce mode ? */
    public function writesPrice(): bool
    {
        return in_array($this, [self::All, self::Price, self::PriceStock], true);
    }

    /** Le stock doit-il être écrit sur un produit existant dans ce mode ? */
    public function writesStock(): bool
    {
        return in_array($this, [self::All, self::Stock, self::PriceStock], true);
    }

    /** Les champs « généraux » (nom, attributs, marque, EAN, dimensions, relations) sont-ils écrits ? */
    public function writesFullRecord(): bool
    {
        return $this === self::All;
    }
}
