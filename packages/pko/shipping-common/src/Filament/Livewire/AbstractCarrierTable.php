<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Base des tables CRUD embarquées dans la page de configuration d'un
 * transporteur. Chaque table est un composant Livewire autonome : une page
 * Filament ne peut héberger qu'une seule table, on en embarque donc plusieurs
 * via @livewire().
 */
abstract class AbstractCarrierTable extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public string $carrierCode = '';

    public function render(): View
    {
        return view('pko-shipping-common::livewire.carrier-table');
    }
}
