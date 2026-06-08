<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Extensions;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Lunar\Admin\Support\Extending\ResourceExtension;

/**
 * Injects the "Frais de port offert" toggle into the product edit form.
 *
 * Use case: dropshipping — the supplier ships directly and shipping is
 * included in our purchase price. Lines flagged pko_free_shipping are
 * excluded from carrier weight/price calculations.
 */
class ProductFreeShippingExtension extends ResourceExtension
{
    public function extendForm(Form $form): Form
    {
        return $form->schema([
            ...$form->getComponents(),
            Section::make('Livraison / Logistique')
                ->schema([
                    Toggle::make('pko_free_shipping')
                        ->label('Frais de port offert')
                        ->helperText(
                            'Cochez si ce produit est expédié directement par le fournisseur (dropshipping) '
                            .'et que les frais de port sont inclus dans votre prix d\'achat. '
                            .'Les lignes de panier correspondantes seront exclues du calcul de livraison.'
                        )
                        ->default(false),
                ])
                ->collapsible()
                ->collapsed(fn ($record) => ! ($record?->pko_free_shipping)),
        ]);
    }
}
