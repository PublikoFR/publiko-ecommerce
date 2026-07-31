<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\FieldTypes\Text;
use Lunar\Models\Brand;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Pko\ShippingCommon\Models\Supplier;

/**
 * Catalogue de test « frais de port » — un produit par cas d'expédition.
 *
 * Couvre les axes de `docs/shipping.md` §5.9→5.15 :
 *   - les 5 valeurs de `pko_port_mode` (inherit / standard / flat / free / quote)
 *   - les 3 branches d'héritage fournisseur (`port_inclus` oui / non / cas_par_cas)
 *   - les 5 tranches de la grille Chronopost (2 / 5 / 10 / 20 / 30 kg) + le hors-grille
 *   - l'exclusion franco explicite et l'override manuel
 *   - la disponibilité (stock Weklo / commande fournisseur / rupture)
 *
 * Toutes les valeurs (poids, prix, stock) sont **déterministes** : aucun random,
 * sinon les scénarios de checkout deviennent instables d'un seed à l'autre.
 *
 * Idempotent et purement additif : un produit dont le SKU existe déjà est ignoré,
 * aucune ligne existante n'est modifiée ni supprimée.
 */
class PkoShippingCasesProductSeeder extends Seeder
{
    /** Préfixe SKU commun — permet de retrouver/purger les produits de test. */
    private const SKU_PREFIX = 'TX-';

    /**
     * @var list<array{
     *   sku:string, name:string, type:string, collection:?string, brand:?string,
     *   port_mode:string, supplier:?string, stock:int, purchasable:string,
     *   weight:float, price:int, franco_eligible:?bool, transport_price:?int, case:string
     * }>
     */
    private const PRODUCTS = [
        // ── Mode standard : parcours des tranches de grille ───────────────────
        [
            'sku' => 'TX-01', 'name' => 'Télécommande 4 canaux bi-directionnelle',
            'type' => 'Accessoire', 'collection' => null, 'brand' => 'SOMFY',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 40, 'purchasable' => 'always',
            'weight' => 1.5, 'price' => 8000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Grille tranche 2 kg — les 3 services sont proposés.',
        ],
        [
            'sku' => 'TX-02', 'name' => 'Motorisation à bras droits 24V',
            'type' => 'Motorisation', 'collection' => 'Motorisations', 'brand' => 'FAAC',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 20, 'purchasable' => 'always',
            'weight' => 7.0, 'price' => 15000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Grille tranche 10 kg.',
        ],
        [
            'sku' => 'TX-03', 'name' => 'Volet roulant rénovation 1200x1000',
            'type' => 'Volet roulant', 'collection' => 'Volets roulants', 'brand' => 'SOMFY',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 10, 'purchasable' => 'always',
            'weight' => 18.0, 'price' => 22000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Grille tranche 20 kg — point relais encore disponible.',
        ],
        [
            'sku' => 'TX-04', 'name' => 'Volet roulant monobloc 1400x1200',
            'type' => 'Volet roulant', 'collection' => 'Volets roulants', 'brand' => 'SOMFY',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 10, 'purchasable' => 'always',
            'weight' => 20.0, 'price' => 20000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Borne exacte 20 kg — vérifie l\'inclusivité du bracket.',
        ],
        [
            'sku' => 'TX-05', 'name' => 'Portail battant acier 2 vantaux 3 m',
            'type' => 'Portail', 'collection' => 'Portails battants', 'brand' => 'CAME',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 8, 'purchasable' => 'always',
            'weight' => 25.0, 'price' => 30000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Au-delà de 20 kg — le point relais doit disparaître.',
        ],
        [
            'sku' => 'TX-06', 'name' => 'Portail coulissant aluminium 3,50 m',
            'type' => 'Portail', 'collection' => 'Portails coulissants', 'brand' => 'NICE',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 5, 'purchasable' => 'always',
            'weight' => 30.0, 'price' => 35000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Borne haute de la grille (30 kg).',
        ],
        [
            'sku' => 'TX-07', 'name' => 'Portail coulissant aluminium renforcé 5 m',
            'type' => 'Portail', 'collection' => 'Portails coulissants', 'brand' => 'NICE',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 3, 'purchasable' => 'always',
            'weight' => 35.0, 'price' => 40000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Hors grille (> 30 kg) — bascule en transport sur devis.',
        ],

        // ── Franco ────────────────────────────────────────────────────────────
        [
            'sku' => 'TX-08', 'name' => 'Kit motorisation coulissant 1000 kg premium',
            'type' => 'Motorisation', 'collection' => 'Motorisations', 'brand' => 'BFT',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 20, 'purchasable' => 'always',
            'weight' => 2.0, 'price' => 60000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Dépasse à lui seul le seuil franco (500 € HT).',
        ],
        [
            'sku' => 'TX-09', 'name' => 'Coulisse longue 4 m (hors normes)',
            'type' => 'Accessoire', 'collection' => null, 'brand' => 'SOMFY',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 10, 'purchasable' => 'always',
            'weight' => 3.0, 'price' => 55000,
            'franco_eligible' => false, 'transport_price' => null,
            'case' => 'Exclu du franco malgré le mode standard — annule le franco du panier.',
        ],

        // ── Mode flat (forfait transport par produit) ─────────────────────────
        [
            'sku' => 'TX-10', 'name' => 'Tablier de volet roulant sur mesure',
            'type' => 'Volet roulant', 'collection' => 'Volets roulants', 'brand' => 'SOMFY',
            'port_mode' => 'flat', 'supplier' => null,
            'stock' => 15, 'purchasable' => 'always',
            'weight' => 12.0, 'price' => 18000,
            'franco_eligible' => false, 'transport_price' => 2500,
            'case' => 'Forfait 25 € HT × quantité, poids exclu du poids taxable.',
        ],
        [
            'sku' => 'TX-11', 'name' => 'Portail autoportant 6 m sur palette',
            'type' => 'Portail', 'collection' => 'Portails coulissants', 'brand' => 'CAME',
            'port_mode' => 'flat', 'supplier' => null,
            'stock' => 5, 'purchasable' => 'always',
            'weight' => 60.0, 'price' => 90000,
            'franco_eligible' => false, 'transport_price' => 12000,
            'case' => 'Forfait 120 € HT — 60 kg qui ne doivent PAS alimenter la grille.',
        ],
        [
            'sku' => 'TX-12', 'name' => 'Clôture rigide premium — lot de 10 panneaux',
            'type' => 'Clôture', 'collection' => 'Clôtures', 'brand' => 'BFT',
            'port_mode' => 'flat', 'supplier' => null,
            'stock' => 10, 'purchasable' => 'always',
            'weight' => 8.0, 'price' => 60000,
            'franco_eligible' => true, 'transport_price' => 4000,
            'case' => 'Override franco manuel sur un mode flat — badge « Forcé manuellement ».',
        ],

        // ── Mode free (port inclus dans le prix d'achat) ──────────────────────
        [
            'sku' => 'TX-13', 'name' => 'Coffre tunnel volet roulant (expédition fabricant)',
            'type' => 'Volet roulant', 'collection' => 'Volets roulants', 'brand' => 'SOMFY',
            'port_mode' => 'free', 'supplier' => null,
            'stock' => 25, 'purchasable' => 'always',
            'weight' => 30.0, 'price' => 25000,
            'franco_eligible' => false, 'transport_price' => null,
            'case' => 'Livraison offerte — 30 kg exclus du poids taxable.',
        ],

        // ── Mode quote (transport sur devis) ─────────────────────────────────
        [
            'sku' => 'TX-14', 'name' => 'Portail coulissant 8 m sur mesure',
            'type' => 'Portail', 'collection' => 'Portails coulissants', 'brand' => 'NICE',
            'port_mode' => 'quote', 'supplier' => null,
            'stock' => 2, 'purchasable' => 'always',
            'weight' => 120.0, 'price' => 150000,
            'franco_eligible' => false, 'transport_price' => null,
            'case' => 'Panier 100 % devis → commande awaiting-quote, pas de paiement.',
        ],
        [
            'sku' => 'TX-15', 'name' => 'Rideau métallique motorisé 5 m',
            'type' => 'Portail', 'collection' => null, 'brand' => 'FAAC',
            'port_mode' => 'quote', 'supplier' => 'Fournisseur Port À Trancher',
            'stock' => 0, 'purchasable' => 'always',
            'weight' => 80.0, 'price' => 90000,
            'franco_eligible' => false, 'transport_price' => null,
            'case' => 'Devis + commande fournisseur — sert aux scénarios de panier mixte.',
        ],

        // ── Mode inherit (résolution par le fournisseur) ─────────────────────
        [
            'sku' => 'TX-16', 'name' => 'Motorisation enterrée — kit complet',
            'type' => 'Motorisation', 'collection' => 'Motorisations', 'brand' => 'BFT',
            'port_mode' => 'inherit', 'supplier' => 'Fournisseur Port Inclus',
            'stock' => 0, 'purchasable' => 'always',
            'weight' => 20.0, 'price' => 30000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'inherit + port_inclus=oui → free. Badge commande fournisseur 3–7 j.',
        ],
        [
            'sku' => 'TX-17', 'name' => 'Moteur de volet roulant radio 20/17',
            'type' => 'Motorisation', 'collection' => 'Motorisations', 'brand' => 'SOMFY',
            'port_mode' => 'inherit', 'supplier' => 'SOMFY',
            'stock' => 0, 'purchasable' => 'always',
            'weight' => 6.0, 'price' => 20000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'inherit + port_inclus=non → standard. Badge commande fournisseur 5–10 j.',
        ],
        [
            'sku' => 'TX-18', 'name' => 'Bras articulés pour portail battant',
            'type' => 'Motorisation', 'collection' => 'Motorisations', 'brand' => 'CAME',
            'port_mode' => 'inherit', 'supplier' => 'Fournisseur Port À Trancher',
            'stock' => 0, 'purchasable' => 'always',
            'weight' => 10.0, 'price' => 26000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'inherit + cas_par_cas → standard + filtre back-office « Port à trancher ».',
        ],
        [
            'sku' => 'TX-19', 'name' => 'Récepteur universel 433 MHz',
            'type' => 'Accessoire', 'collection' => null, 'brand' => 'NICE',
            'port_mode' => 'inherit', 'supplier' => null,
            'stock' => 12, 'purchasable' => 'always',
            'weight' => 4.0, 'price' => 12000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'inherit sans fournisseur → standard.',
        ],

        // ── Disponibilité ────────────────────────────────────────────────────
        [
            'sku' => 'TX-20', 'name' => 'Photocellule infrarouge sans fil',
            'type' => 'Accessoire', 'collection' => null, 'brand' => 'FAAC',
            'port_mode' => 'standard', 'supplier' => null,
            'stock' => 0, 'purchasable' => 'in_stock',
            'weight' => 5.0, 'price' => 9000,
            'franco_eligible' => true, 'transport_price' => null,
            'case' => 'Rupture de stock — produit non ajoutable au panier.',
        ],
    ];

    public function run(): void
    {
        $currency = Currency::query()->where('code', 'EUR')->firstOrFail();
        $taxClass = TaxClass::query()->where('default', true)->firstOrFail();

        $types = ProductType::query()->pluck('id', 'name');
        $brands = Brand::query()->pluck('id', 'name');
        $suppliers = Supplier::query()->pluck('id', 'name');
        $collections = LunarCollection::query()
            ->get()
            ->mapWithKeys(fn (LunarCollection $c) => [$c->translateAttribute('name') => $c->id]);

        foreach (self::PRODUCTS as $row) {
            if (ProductVariant::query()->where('sku', $row['sku'])->exists()) {
                continue;
            }

            $typeId = $types[$row['type']] ?? $types->first();

            $product = Product::query()->create([
                'product_type_id' => $typeId,
                'status' => 'published',
                'brand_id' => $row['brand'] !== null ? ($brands[$row['brand']] ?? null) : null,
                'attribute_data' => collect([
                    'name' => new Text($row['name']),
                    'description' => new Text($row['name'].' — '.$row['case']),
                ]),
            ]);

            $product->pko_port_mode = $row['port_mode'];
            $product->pko_franco_eligible = $row['franco_eligible'] ?? true;
            $product->pko_transport_price_cents = $row['transport_price'];
            $product->pko_supplier_id = $row['supplier'] !== null
                ? ($suppliers[$row['supplier']] ?? null)
                : null;
            $product->save();

            if ($row['collection'] !== null && isset($collections[$row['collection']])) {
                $product->collections()->sync([$collections[$row['collection']]]);
            }

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'tax_class_id' => $taxClass->id,
                'sku' => $row['sku'],
                'mpn' => $row['sku'].'-MPN',
                'shippable' => true,
                'stock' => $row['stock'],
                'backorder' => 0,
                'purchasable' => $row['purchasable'],
                'unit_quantity' => 1,
                'length_value' => 60,
                'length_unit' => 'cm',
                'width_value' => 40,
                'width_unit' => 'cm',
                'height_value' => 30,
                'height_unit' => 'cm',
                'weight_value' => $row['weight'],
                'weight_unit' => 'kg',
            ]);

            $variant->prices()->create([
                'price' => $row['price'],
                'currency_id' => $currency->id,
                'min_quantity' => 1,
                'customer_group_id' => null,
            ]);
        }
    }

    /** Préfixe SKU des produits de test (utilisé par les commandes de purge éventuelles). */
    public static function skuPrefix(): string
    {
        return self::SKU_PREFIX;
    }
}
