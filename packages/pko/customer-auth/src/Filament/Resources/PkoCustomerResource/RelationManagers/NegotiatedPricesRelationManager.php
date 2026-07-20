<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use Lunar\Models\Currency;
use Lunar\Models\ProductVariant;

/**
 * Onglet « Prix négociés » sur la fiche client : gère les tarifs HT contractuels
 * propres au client. Recherche par nom/référence produit + saisie du prix HT en €.
 * Les valeurs sont stockées en centimes ; l'application au storefront/panier passe
 * par NegotiatedPricePipeline.
 */
class NegotiatedPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'negotiatedPrices';

    protected static ?string $title = 'Prix négociés';

    protected static ?string $icon = 'heroicon-o-currency-euro';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_variant_id')
                ->label('Produit')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => static::searchVariants($search))
                ->getOptionLabelUsing(fn ($value): ?string => static::variantLabel(ProductVariant::find($value)))
                ->required()
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('customer_id', $this->getOwnerRecord()->getKey()),
                )
                ->validationMessages([
                    'unique' => 'Ce produit a déjà un prix négocié pour ce client.',
                ])
                ->columnSpanFull(),
            Forms\Components\TextInput::make('price')
                ->label('Prix négocié HT')
                ->helperText("Prix d'achat HT réservé à ce client. Une promotion ou un prix dégressif par quantité peut descendre en dessous.")
                ->numeric()
                ->minValue(0)
                ->required()
                ->prefix('€')
                ->formatStateUsing(fn ($state) => $state === null ? null : number_format(((int) $state) / 100, 2, '.', ''))
                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Prix négociés')
            ->description('Tarifs HT spécifiques à ce client, appliqués automatiquement sur le storefront et au panier.')
            ->columns([
                Tables\Columns\TextColumn::make('product')
                    ->label('Produit')
                    ->getStateUsing(fn ($record): ?string => static::variantLabel($record->productVariant)),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prix HT')
                    ->getStateUsing(fn ($record): string => number_format(((int) $record->price) / 100, 2, ',', ' ').' €'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter un prix négocié')
                    ->mutateFormDataUsing(fn (array $data): array => static::withDefaultCurrency($data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Aucun prix négocié')
            ->emptyStateDescription('Ajoutez des tarifs HT spécifiques à ce client.');
    }

    /**
     * @return array<int, string>
     */
    protected static function searchVariants(string $search): array
    {
        return ProductVariant::query()
            ->with('product')
            ->where('sku', 'like', "%{$search}%")
            ->orWhereHas('product', fn ($q) => $q->whereRaw(
                'JSON_UNQUOTE(JSON_EXTRACT(attribute_data, "$.name.value")) LIKE ?',
                ["%{$search}%"]
            ))
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => static::variantLabel($variant)])
            ->all();
    }

    protected static function variantLabel(?ProductVariant $variant): ?string
    {
        if ($variant === null) {
            return null;
        }

        $name = (string) ($variant->product?->translateAttribute('name') ?? '');
        $sku = $variant->sku;

        return trim($name.($sku ? " ({$sku})" : '')) ?: "Variante #{$variant->id}";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function withDefaultCurrency(array $data): array
    {
        $data['currency_id'] = Currency::getDefault()?->id;

        return $data;
    }
}
