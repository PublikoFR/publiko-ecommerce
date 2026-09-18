{{--
    Surcharge de la vue Lunar (vendor/lunarphp/lunar/resources/views/infolists/components/timeline.blade.php).
    Le titre n'est rendu que s'il est renseigné : sur la fiche commande, la timeline est placée
    dans une section qui porte déjà le titre, et un titre vide laissait un espacement parasite.
--}}
<section @class(['space-y-6' => filled($getLabel())])>
    @if (filled($getLabel()))
        <x-filament::section.heading>
            {{ $getLabel() }}
        </x-filament::section.heading>
    @endif

    @livewire('lunar.admin.livewire.components.activity-log-feed', [
        'subject' => $getRecord()
    ])
</section>
