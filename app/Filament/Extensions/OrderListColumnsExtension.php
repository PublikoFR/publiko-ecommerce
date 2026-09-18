<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;

/**
 * Colonnes de la liste des commandes admin, dans l'ordre :
 * Date · Référence · Client (nom, e-mail, téléphone) · Statut (statut + nouveau/récurrent) · Total.
 *
 * Remplace les 11 colonnes Lunar : référence client, étiquettes et code postal
 * disparaissent, e-mail et téléphone rejoignent la cellule client, le type de
 * client passe sous le badge de statut. Filtres, actions et tri par défaut
 * (id décroissant) restent ceux de Lunar.
 */
final class OrderListColumnsExtension extends ResourceExtension
{
    public function extendTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('placed_at')
                    ->label('Date')
                    ->dateTime('d/m/y - H\hi')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable(),
                TextColumn::make('billingAddress.fullName')
                    ->label('Client')
                    ->formatStateUsing(fn (string $state, Order $record): HtmlString => self::customerCell($state, $record))
                    ->placeholder('—')
                    ->searchable(['first_name', 'last_name', 'contact_email', 'contact_phone']),
                ViewColumn::make('status')
                    ->label('Statut')
                    ->view('filament.orders.list-status-cell'),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => $state->formatted),
            ])
            // Remplace le modifyQueryUsing de Lunar (with currency) : on le reprend
            // et on précharge l'adresse de facturation lue par la cellule client.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['currency', 'billingAddress']));
    }

    private static function customerCell(string $name, Order $record): HtmlString
    {
        $address = $record->billingAddress;

        $lines = ['<span class="font-medium">'.e($name).'</span>'];

        foreach ([$address?->contact_email, $address?->contact_phone] as $line) {
            if (filled($line)) {
                $lines[] = '<span class="text-gray-500 dark:text-gray-400">'.e($line).'</span>';
            }
        }

        return new HtmlString('<div class="flex flex-col text-sm leading-5">'.implode('', $lines).'</div>');
    }
}
