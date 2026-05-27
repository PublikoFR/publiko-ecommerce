<?php

declare(strict_types=1);

namespace Pko\AiImporter\Support;

use Pko\AiImporter\Services\LunarProductWriter;

/**
 * Canonical list of product target fields offered in the mapping editor.
 *
 * The *keys* are the output keys consumed by {@see LunarProductWriter}
 * (they end up in `config_data.mapping[*]`). The *labels* are PrestaShop-style
 * French field names shown in the « champ cible » dropdown.
 *
 * Branding neutral: no client name, only generic e-commerce field labels.
 */
final class ProductFieldCatalog
{
    /**
     * Grouped option set, ready for a Filament Select (`->options()`).
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return [
            'Identité' => [
                'name' => 'Nom',
                'reference' => 'Référence',
                'sku' => 'Réf. fournisseur (SKU)',
                'brand_name' => 'Marque',
                'ean' => 'EAN-13',
                'product_type_handle' => 'Type de produit',
            ],
            'Prix & taxe' => [
                'price_cents' => 'Prix HT (cents)',
                'compare_price_cents' => 'Prix barré (cents)',
                'tax_class_handle' => 'Classe de taxe',
            ],
            'Stock' => [
                'stock' => 'Stock',
            ],
            'Dimensions' => [
                'weight_value' => 'Poids',
                'weight_unit' => 'Unité de poids',
                'width_value' => 'Largeur',
                'height_value' => 'Hauteur',
                'depth_value' => 'Profondeur',
                'length_value' => 'Longueur',
            ],
            'Contenu' => [
                'description' => 'Description',
                'description_short' => 'Description courte',
                'meta_title' => 'Méta titre',
                'meta_description' => 'Méta description',
                'meta_keywords' => 'Méta mots-clés',
                'url_key' => 'URL (slug)',
            ],
            'Relations' => [
                'collections' => 'Catégories / Collections',
                'features' => 'Caractéristiques',
                'images' => 'Images',
                'videos' => 'Vidéos',
            ],
        ];
    }

    /**
     * Flat key => label map (all groups merged).
     *
     * @return array<string, string>
     */
    public static function flat(): array
    {
        $flat = [];
        foreach (self::groupedOptions() as $fields) {
            foreach ($fields as $key => $label) {
                $flat[$key] = $label;
            }
        }

        return $flat;
    }

    public static function label(string $key): string
    {
        return self::flat()[$key] ?? $key;
    }
}
