<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\Models\Brand;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

class MdeProductSeeder extends Seeder
{
    /**
     * @var list<array{type:string, names:list<string>}>
     */
    private const CATALOG = [
        [
            'type' => 'Portail',
            'names' => [
                'Portail coulissant aluminium RAL 7016',
                'Portail coulissant PVC blanc',
                'Portail battant acier galvanisé',
                'Portail autoportant design',
                'Portail coulissant ajouré',
                'Portail battant 2 vantaux',
                'Portail PVC plein',
                'Portail alu contemporain',
                'Portail coulissant 4m',
                'Portail battant aluminium ajouré',
            ],
        ],
        [
            'type' => 'Volet roulant',
            'names' => [
                'Volet roulant électrique 1200x1200',
                'Volet roulant solaire Premium',
                'Volet roulant rénovation alu',
                'Volet roulant coffre tunnel',
                'Volet roulant Radio RTS',
                'Volet roulant thermique',
                'Volet roulant sécurité ABZ',
                'Volet roulant 1000x1400',
                'Volet roulant avec lame finale',
                'Volet roulant monobloc',
            ],
        ],
        [
            'type' => 'Motorisation',
            'names' => [
                'Motorisation portail coulissant 500kg',
                'Motorisation portail battant hydraulique',
                'Motorisation enterrée',
                'Motorisation à bras articulés',
                'Motorisation à bras droits',
                'Motorisation portail coulissant 1000kg',
                'Motorisation bras SOMFY Ixengo',
                'Motorisation portail battant 24V',
                'Motorisation universelle',
                'Kit motorisation complet 2 télécommandes',
            ],
        ],
        [
            'type' => 'Clôture',
            'names' => [
                'Panneau rigide 2000x1730',
                'Panneau rigide H1800 galvanisé',
                'Clôture aluminium moderne',
                'Clôture composite imitation bois',
                'Clôture panneaux pleins',
                'Clôture à mailles soudées',
                'Panneau double fil',
                'Clôture brise-vue',
                'Clôture rigide premium',
                'Clôture PVC plein',
            ],
        ],
        [
            'type' => 'Accessoire',
            'names' => [
                'Digicode filaire extérieur',
                'Interphone vidéo 7 pouces',
                'Télécommande 4 canaux',
                'Récepteur universel 433MHz',
                'Boucle magnétique détection',
                'Feu clignotant LED',
                'Photocellule infrarouge',
                'Jeu de crémaillères 1m',
                'Serrure électrique 12V',
                'Kit batterie secours',
            ],
        ],
    ];

    public function run(): void
    {
        $currency = Currency::query()->where('code', 'EUR')->firstOrFail();
        $taxClass = TaxClass::query()->where('default', true)->firstOrFail();
        $installateurs = CustomerGroup::query()->where('handle', 'installateurs')->firstOrFail();

        $brands = Brand::query()->pluck('id')->all();
        $collections = LunarCollection::query()->pluck('id')->all();

        foreach (self::CATALOG as $block) {
            $type = ProductType::query()->where('name', $block['type'])->firstOrFail();

            foreach ($block['names'] as $name) {
                if (Product::query()->whereJsonContains('attribute_data->name->value', $name)->exists()) {
                    continue;
                }

                $product = Product::query()->create([
                    'product_type_id' => $type->id,
                    'status' => 'published',
                    'brand_id' => $brands[array_rand($brands)],
                    'attribute_data' => collect([
                        'name' => new Text($name),
                        'description' => new Text("{$name} — produit professionnel distribué par MDE Distribution."),
                    ]),
                ]);

                if ($collections !== []) {
                    $product->collections()->sync([
                        $collections[array_rand($collections)],
                    ]);
                }

                $sku = strtoupper(Str::random(4)).'-'.random_int(1000, 9999);
                $basePriceCents = random_int(5000, 250000);
                $stock = random_int(0, 50);

                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'tax_class_id' => $taxClass->id,
                    'sku' => $sku,
                    'gtin' => (string) random_int(1000000000000, 9999999999999),
                    'mpn' => $sku.'-MPN',
                    'ean' => (string) random_int(1000000000000, 9999999999999),
                    'shippable' => true,
                    'stock' => $stock,
                    'backorder' => 0,
                    'purchasable' => 'always',
                    'unit_quantity' => 1,
                    'length_value' => random_int(20, 400),
                    'length_unit' => 'cm',
                    'width_value' => random_int(20, 400),
                    'width_unit' => 'cm',
                    'height_value' => random_int(10, 250),
                    'height_unit' => 'cm',
                    'weight_value' => random_int(1, 80),
                    'weight_unit' => 'kg',
                ]);

                $variant->prices()->create([
                    'price' => $basePriceCents,
                    'compare_price' => (int) round($basePriceCents * 1.2),
                    'currency_id' => $currency->id,
                    'min_quantity' => 1,
                    'customer_group_id' => null,
                ]);

                $variant->prices()->create([
                    'price' => (int) round($basePriceCents * 0.85),
                    'compare_price' => $basePriceCents,
                    'currency_id' => $currency->id,
                    'min_quantity' => 1,
                    'customer_group_id' => $installateurs->id,
                ]);
            }
        }
    }
}
