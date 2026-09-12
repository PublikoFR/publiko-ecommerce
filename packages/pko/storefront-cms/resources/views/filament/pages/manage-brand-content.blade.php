<x-filament-panels::page>
    {{-- Métadonnées SEO repliées par défaut : le page-builder démarre en haut de page,
         aligné avec la sidebar de sous-navigation (SubNavigationPosition::End). --}}
    <x-filament::section
        :heading="__('pko-storefront-cms::admin.brand_content.seo_section')"
        collapsible
        collapsed
        icon="heroicon-o-magnifying-glass"
    >
        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end gap-2">
                <x-filament::button type="submit">
                    {{ __('pko-storefront-cms::admin.brand_content.save_meta') }}
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @livewire('pko-page-builder', [
        'modelClass' => \Pko\StorefrontCms\Models\BrandPage::class,
        'recordId' => $brandPage->getKey(),
    ], key('brand-page-builder-'.$brandPage->getKey()))
</x-filament-panels::page>
