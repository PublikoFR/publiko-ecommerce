<x-filament-panels::page>
    {{-- Réglages propres à l'e-mail (objet, activation, rappel des variables) :
         form Filament classique, avec son propre bouton d'enregistrement. --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top:1rem;">
            <x-filament::button type="submit">
                {{ __('pko-mail-templates::admin.action.save_settings') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Contenu du message : même éditeur de blocs que les pages et articles.
         `withMeta: false` désactive le bagage CMS (titre, slug, SEO, couverture)
         qui n'a aucun sens pour un e-mail. Le composant lit et écrit lui-même
         la colonne `content` et porte sa propre barre d'enregistrement. --}}
    <div style="margin-top:1.5rem;">
        @livewire('pko-page-builder', [
            'modelClass' => $this->getResource()::getModel(),
            'recordId' => $this->record->getKey(),
            'withMeta' => false,
            'indexUrl' => $this->getResource()::getUrl('index'),
        ], key('mail-template-builder-'.$this->record->getKey()))
    </div>
</x-filament-panels::page>
