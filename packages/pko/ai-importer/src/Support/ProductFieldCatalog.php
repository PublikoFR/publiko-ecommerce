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
        return self::flat()[$key] ?? self::LEGACY_LABELS[$key] ?? $key;
    }

    /**
     * Libellés amis pour les clés PrestaShop sans équivalent canonique Lunar
     * (conservées telles quelles dans le mapping — le remappage vers une clé
     * canonique se fait au write via `LunarProductWriter::normalizeLegacyKeys()`).
     * Sert à afficher « Prix HT » plutôt que `price_tex` dans le blueprint.
     *
     * @var array<string, string>
     */
    public const LEGACY_LABELS = [
        'id' => 'ID PrestaShop',
        'mpn' => 'MPN',
        'upc' => 'UPC',
        'ean13' => 'EAN-13',
        'price_tex' => 'Prix HT',
        'wholesale_price' => 'Prix d\'achat',
        'quantity' => 'Quantité',
        'manufacturer' => 'Marque',
        'supplier' => 'Fournisseur',
        'supplier_reference' => 'Réf. fournisseur',
        'reference' => 'Référence',
        'category' => 'Catégories',
        'link_rewrite' => 'URL (slug)',
        'image' => 'Images',
        'image_alt' => 'Alt images',
        'width' => 'Largeur',
        'height' => 'Hauteur',
        'depth' => 'Profondeur',
        'weight' => 'Poids',
        'active' => 'Actif (0/1)',
        'on_sale' => 'En promo (0/1)',
        'show_price' => 'Afficher le prix (0/1)',
        'visibility' => 'Visibilité',
        'condition' => 'État du produit',
        'unity' => 'Unité',
        'ecotax' => 'Éco-taxe',
        'unit_price' => 'Prix unitaire',
        'reduction_price' => 'Remise (montant)',
        'reduction_percent' => 'Remise (%)',
        'reduction_from' => 'Remise du',
        'reduction_to' => 'Remise au',
        'id_tax_rules_group' => 'Règle de taxe (ID)',
        'minimal_quantity' => 'Quantité minimale',
        'low_stock_threshold' => 'Seuil stock bas',
        'available_now' => 'Dispo (en stock)',
        'available_later' => 'Dispo (sur commande)',
        'delivery_in_stock' => 'Livraison (en stock)',
        'delivery_out_stock' => 'Livraison (rupture)',
        'delete_existing_images' => 'Supprimer images existantes',
        'additional_shipping_cost' => 'Frais de port additionnels',
    ];
}
