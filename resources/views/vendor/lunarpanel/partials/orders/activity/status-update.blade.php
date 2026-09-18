{{--
    Surcharge de la vue Lunar (vendor/lunarphp/lunar/resources/views/partials/orders/activity/status-update.blade.php).
    Libellé et badges étaient sur une seule ligne : dans la colonne latérale de la fiche commande,
    les deux statuts se retrouvaient tronqués. Les badges passent sous le libellé et peuvent
    revenir à la ligne.
--}}
<div>
    <p>{{ __('lunarpanel::components.activity-log.partials.orders.status_change') }}</p>

    <div class="mt-1.5 flex flex-wrap items-center gap-x-1 gap-y-1.5">
        <x-filament::badge :color="$previousStatusColor">
            {{ $previousStatusLabel }}
        </x-filament::badge>

        @svg('heroicon-m-chevron-right', ['class' => 'h-4 w-4 shrink-0 text-gray-400'])

        <x-filament::badge :color="$newStatusColor">
            {{ $newStatusLabel }}
        </x-filament::badge>
    </div>
</div>
