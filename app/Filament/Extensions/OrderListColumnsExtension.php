<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use App\Support\Orders\OrderCompanyName;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;

/**
 * Colonnes de la liste des commandes admin, dans l'ordre :
 * Date · Référence · Client (raison sociale, nom, e-mail, téléphone) · Statut (statut + nouveau/récurrent) · Total.
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
                    // État calculé : une adresse avec raison sociale mais sans nom ne doit
                    // pas retomber sur le placeholder.
                    ->state(fn (Order $record): ?HtmlString => self::customerCell($record))
                    ->placeholder('—')
                    ->searchable(['company_name', 'first_name', 'last_name', 'contact_email', 'contact_phone']),
                ViewColumn::make('status')
                    ->label('Statut')
                    ->view('filament.orders.list-status-cell'),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state): string => $state->formatted),
            ])
            // Remplace le modifyQueryUsing de Lunar (with currency) : on le reprend
            // et on précharge l'adresse de facturation et le client lus par la cellule client.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['currency', 'billingAddress', 'customer']));
    }

    private static function customerCell(Order $record): ?HtmlString
    {
        $address = $record->billingAddress;
        $company = OrderCompanyName::for($record);
        $name = trim((string) $address?->fullName);

        $lines = [];

        if ($company !== null) {
            $lines[] = '<span class="font-semibold">'.e($company).'</span>';
        }

        if ($name !== '') {
            $lines[] = '<span'.($company === null ? ' class="font-medium"' : '').'>'.e($name).'</span>';
        }

        foreach ([$address?->contact_email, $address?->contact_phone] as $line) {
            if (filled($line)) {
                $lines[] = '<span class="text-gray-500 dark:text-gray-400">'.e($line).'</span>';
            }
        }

        if ($lines === []) {
            return null;
        }

        return new HtmlString('<div class="flex flex-col text-sm leading-5">'.implode('', $lines).'</div>');
    }
}
