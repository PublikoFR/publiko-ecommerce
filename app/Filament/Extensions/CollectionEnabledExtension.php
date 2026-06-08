<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Lunar\Admin\Support\Extending\ResourceExtension;

/**
 * Adds a pko_enabled toggle to the Collection edit form.
 *
 * Disabling a collection hides it and all its descendants from storefront navigation.
 * Products whose only collection is disabled are also hidden from the storefront.
 */
class CollectionEnabledExtension extends ResourceExtension
{
    public function extendForm(Form $form): Form
    {
        return $form->schema([
            ...$form->getComponents(),
            Section::make('Visibilité')
                ->schema([
                    Toggle::make('pko_enabled')
                        ->label('Catégorie activée')
                        ->helperText(
                            'Désactiver cette catégorie la masque du menu, '
                            .'renvoie une 404 sur sa page et masque les produits '
                            .'qui n\'appartiennent qu\'à elle. '
                            .'La désactivation se propage en cascade à toutes les sous-catégories.'
                        )
                        ->default(true),
                ])
                ->collapsible()
                ->collapsed(fn ($record): bool => (bool) ($record?->pko_enabled ?? true)),
        ]);
    }
}
