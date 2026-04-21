<x-filament-panels::page>
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="flex justify-end gap-2">
            <x-filament::button type="submit">
                Enregistrer les métadonnées
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6">
        @livewire('pko-page-builder', [
            'modelClass' => \Pko\StorefrontCms\Models\BrandPage::class,
            'recordId' => $brandPage->getKey(),
        ], key('brand-page-builder-'.$brandPage->getKey()))
    </div>
</x-filament-panels::page>
