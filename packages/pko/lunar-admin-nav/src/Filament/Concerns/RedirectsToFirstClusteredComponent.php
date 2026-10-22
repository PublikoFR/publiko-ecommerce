<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Concerns;

use Filament\Facades\Filament;

/**
 * Le `mount()` natif d'un Cluster redirige vers le premier item de SA sous-nav.
 * Quand la sous-nav on-page est masquée (`admin-nav.hide_cluster_subnav`), il n'y
 * a plus d'item : la page s'affiche sans vue et lève « $view must not be
 * accessed before initialization » (lien de fil d'Ariane vers le cluster).
 * On redirige donc vers le premier composant du cluster accessible à l'utilisateur,
 * sous-nav affichée ou non.
 */
trait RedirectsToFirstClusteredComponent
{
    public function mount(): void
    {
        foreach (Filament::getClusteredComponents(static::class) as $component) {
            if ($component::canAccess()) {
                $this->redirect($component::getUrl());

                return;
            }
        }
    }
}
