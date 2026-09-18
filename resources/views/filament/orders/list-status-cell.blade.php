@php
    $record = $getRecord();
@endphp

{{-- Liste des commandes : statut, puis nouveau / récurrent sur la ligne suivante. --}}
<div class="flex flex-col items-start gap-1 px-3 py-4">
    <x-filament::badge :color="\Lunar\Admin\Support\OrderStatus::getColor($record->status)">
        {{ \Lunar\Admin\Support\OrderStatus::getLabel($record->status) }}
    </x-filament::badge>

    <x-filament::badge
        :color="\Lunar\Admin\Support\CustomerStatus::getColor((bool) $record->new_customer)"
        :icon="\Lunar\Admin\Support\CustomerStatus::getIcon((bool) $record->new_customer)"
    >
        {{ \Lunar\Admin\Support\CustomerStatus::getLabel((bool) $record->new_customer) }}
    </x-filament::badge>
</div>
