<x-filament-panels::page>
    {{-- Éditeur unifié : métadonnées (onglet Page), blocs (onglet Blocs),
         titre H1 on-page et barre Enregistrer/Publier sont tous portés par le
         composant Livewire. Le form Filament n'est plus rendu ici. --}}
    @livewire('pko-page-builder', [
        'modelClass' => $this->getResource()::getModel(),
        'recordId' => $this->record->getKey(),
        'withMeta' => true,
        'indexUrl' => $this->getResource()::getUrl('index'),
    ], key('page-builder-'.$this->record->getKey()))
</x-filament-panels::page>
