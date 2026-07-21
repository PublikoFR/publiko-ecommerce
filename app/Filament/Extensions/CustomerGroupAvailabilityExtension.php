<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Table;
use Lunar\Admin\Support\Extending\RelationManagerExtension;

/**
 * Ajoute « Retirer la restriction » à l'onglet Disponibilité (collections et
 * produits), et explique la sémantique inversée dans la description du tableau.
 *
 * Le `CustomerGroupRelationManager` de Lunar n'expose qu'`AttachAction` et
 * `EditAction` — aucune `DetachAction`. Dans le modèle opt-in d'origine c'est
 * logique : la ligne existe toujours, on bascule ses interrupteurs. Avec notre
 * sémantique « pas de ligne = visible » (cf. App\Support\CatalogAvailability),
 * supprimer la ligne EST l'opération qui lève la restriction — sans cette action,
 * une restriction posée depuis l'admin ne pourrait plus être annulée que en base.
 *
 * Le même RelationManager sert les deux ressources : Lunar\Admin\...\CollectionResource
 * importe celui de ProductResource. Une seule extension couvre donc les deux.
 */
class CustomerGroupAvailabilityExtension extends RelationManagerExtension
{
    public function extendTable(Table $table): Table
    {
        return $table
            ->description(
                "Par défaut, tous les groupes clients y ont accès. N'ajoutez un groupe ".
                'ici que pour le restreindre : les interrupteurs décochés limitent ce '.
                "que ce groupe peut voir ou acheter. Retirer la ligne rétablit l'accès complet."
            )
            ->actions([
                ...$table->getActions(),
                DetachAction::make()
                    ->label('Retirer la restriction')
                    ->modalHeading('Rétablir l’accès complet pour ce groupe ?')
                    ->modalDescription(
                        'Le groupe retrouvera l’accès par défaut, comme tous les autres groupes.'
                    ),
            ]);
    }
}
