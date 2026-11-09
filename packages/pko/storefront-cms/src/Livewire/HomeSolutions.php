<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Livewire;

use Illuminate\View\View;
use Livewire\Component;
use Pko\StorefrontCms\Support\HomeSolutionZones;

/**
 * Module « À chaque installation, sa solution » de la home (FEAT-009) :
 * schéma isométrique cliquable + liste des familles, synchronisés côté client
 * (Alpine). Aucune interaction serveur : le composant ne fait que résoudre le
 * mapping zone → collection au rendu.
 */
class HomeSolutions extends Component
{
    public function render(): View
    {
        return view('storefront-cms::livewire.home-solutions', [
            'items' => HomeSolutionZones::resolve(config('storefront.home.solutions', [])),
            'viewBox' => HomeSolutionZones::VIEWBOX,
        ]);
    }
}
