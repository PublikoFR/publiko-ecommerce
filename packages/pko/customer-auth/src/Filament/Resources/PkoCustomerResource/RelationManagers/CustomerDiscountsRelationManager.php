<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Resources\PkoCustomerResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Pko\CustomerAuth\Filament\Resources\CustomerDiscountResource;

/**
 * Onglet « Remises » de la fiche client : remises % permanentes sur une
 * catégorie (et sa descendance), une famille fabricant ou une marque.
 * Formulaire et colonnes partagés avec la vue globale CustomerDiscountResource.
 * Application au prix : CustomerDiscountResolver (plus forte remise, puis le plus
 * bas entre prix négocié et prix remisé).
 */
class CustomerDiscountsRelationManager extends RelationManager
{
    protected static string $relationship = 'customerDiscounts';

    protected static ?string $title = 'Remises';

    protected static ?string $icon = 'heroicon-o-receipt-percent';

    protected static ?string $modelLabel = 'remise';

    protected static ?string $pluralModelLabel = 'remises';

    /**
     * Éditable aussi depuis la fiche (page View, celle ouverte par défaut) :
     * Filament rend les relation managers en lecture seule sur une page View.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema(
            CustomerDiscountResource::targetFields(fn (): int => (int) $this->getOwnerRecord()->getKey()),
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Remises')
            ->description('Remises % permanentes. Pas de cumul : la plus forte remise couvrant un produit s\'applique, et le prix le plus bas l\'emporte face à un prix négocié.')
            ->columns(CustomerDiscountResource::discountColumns())
            ->defaultSort('updated_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Ajouter une remise'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Aucune remise')
            ->emptyStateDescription('Ajoutez une remise sur une catégorie, une famille fabricant ou une marque.');
    }
}
