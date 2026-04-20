<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PkoProductResource\Pages\EditProductUnified;
use App\Filament\Resources\PkoProductResource\Pages\PkoListProducts;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Override de la Resource produit Lunar pour substituer l'édition multi-onglets
 * par une page unifiée 2 colonnes. La sous-navigation est masquée, les autres
 * sous-pages Lunar restent accessibles par URL directe (compatibilité).
 */
class PkoProductResource extends ProductResource
{
    protected static ?string $slug = 'products';

    public static function getDefaultSubNavigation(): array
    {
        return [];
    }

    public static function getDefaultPages(): array
    {
        return array_merge(parent::getDefaultPages(), [
            'index' => PkoListProducts::route('/'),
            'edit' => EditProductUnified::route('/{record}/edit'),
        ]);
    }

    /**
     * Préfixe la table avec une miniature issue du premier média
     * attaché via `pko_mediables` (mediagroup = 'product', position 0).
     */
    public static function getTableColumns(): array
    {
        $placeholder = 'data:image/svg+xml;utf8,'.rawurlencode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
            .'<rect width="40" height="40" fill="#f3f4f6"/>'
            .'<path d="M12 14h16v12H12z M16 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z M14 26l4-4 3 3 4-5 3 6z" '
            .'fill="none" stroke="#9ca3af" stroke-width="1.2" stroke-linejoin="round"/></svg>'
        );

        $thumbnail = ImageColumn::make('pko_thumbnail')
            ->label('Image')
            ->square()
            ->size(40)
            ->toggleable()
            ->defaultImageUrl($placeholder)
            ->getStateUsing(function (Product $record) use ($placeholder): string {
                $media = Media::query()
                    ->join('pko_mediables', 'pko_mediables.media_id', '=', 'media.id')
                    ->where('pko_mediables.mediable_type', Product::class)
                    ->where('pko_mediables.mediable_id', $record->id)
                    ->where('pko_mediables.mediagroup', 'product')
                    ->orderBy('pko_mediables.position')
                    ->select('media.*')
                    ->first();

                return $media?->getUrl() ?? $placeholder;
            });

        $brand = TextColumn::make('brand.name')
            ->label('Marque')
            ->toggleable()
            ->searchable()
            ->sortable();

        $name = parent::getNameTableColumn();

        $price = TextColumn::make('base_price')
            ->label('Prix')
            ->toggleable()
            ->getStateUsing(function (Product $record): string {
                /** @var ProductVariant|null $variant */
                $variant = $record->variants->first();
                if (! $variant) {
                    return '—';
                }
                $base = $variant->prices
                    ->where('customer_group_id', null)
                    ->where('min_quantity', '<=', 1)
                    ->first();

                return $base?->price?->formatted() ?? '—';
            });

        $sku = parent::getSkuTableColumn()->label('Réf.');

        $stock = TextColumn::make('variants_sum_stock')
            ->label('Stock')
            ->sum('variants', 'stock');

        $mainCategory = TextColumn::make('main_category')
            ->label('Catégorie principale')
            ->toggleable()
            ->getStateUsing(function (Product $record): string {
                $collection = $record->collections->first();

                return $collection?->translateAttribute('name') ?? '—';
            });

        return [$thumbnail, $brand, $name, $price, $sku, $stock, $mainCategory];
    }

    // ------- Global search (barre recherche admin en haut) -------

    public static function getGloballySearchableAttributes(): array
    {
        // Lunar-only par défaut : variants.sku + tags.value. On ajoute ean, mpn, brand.name.
        return [
            'variants.sku',
            'variants.ean',
            'variants.mpn',
            'brand.name',
            'tags.value',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['variants', 'brand']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->translateAttribute('name') ?: '#'.$record->id;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Marque' => $record->brand?->name ?? '—',
            'Réf.' => $record->variants->first()?->sku ?? '—',
            'Stock' => (string) ($record->variants->first()?->stock ?? 0),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('edit', ['record' => $record]);
    }
}
