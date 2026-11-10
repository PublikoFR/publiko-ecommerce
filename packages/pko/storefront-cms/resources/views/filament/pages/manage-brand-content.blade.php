<x-filament-panels::page>
    {{-- Fiche marque unifiée : informations, logo et référence en accordéons
         repliés, puis le page-builder pleine largeur sous les onglets
         (SubNavigationPosition::Top, cf. AdminNavPlugin). Le bouton
         « Enregistrer » de la barre du builder enregistre aussi ce formulaire. --}}
    <form wire:submit="save">
        {{ $this->form }}
    </form>

    @livewire('pko-page-builder', [
        'modelClass' => \Pko\StorefrontCms\Models\BrandPage::class,
        'recordId' => $brandPage->getKey(),
    ], key('brand-page-builder-'.$brandPage->getKey()))
</x-filament-panels::page>
