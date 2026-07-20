<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Lunar\Admin\Support\Extending\ResourceExtension;

/**
 * Ajoute le champ « Métier » aux groupes clients. Un groupe marqué métier est
 * proposé dans la liste déroulante du formulaire d'inscription front, pour
 * typer le nouveau client dès la création (cf. RegisterProCustomer).
 */
class CustomerGroupFieldsExtension extends ResourceExtension
{
    public function extendForm(Form $form): Form
    {
        return $form->schema([
            ...$form->getComponents(),
            Toggle::make('pko_is_metier')
                ->label('Métier')
                ->helperText('Proposé dans la liste déroulante du formulaire d\'inscription pour typer le nouveau client.')
                ->default(false),
        ]);
    }

    public function extendTable(Table $table): Table
    {
        return $table->pushColumns([
            IconColumn::make('pko_is_metier')
                ->label('Métier')
                ->boolean()
                ->sortable(),
        ]);
    }
}
