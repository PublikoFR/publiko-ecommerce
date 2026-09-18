{{-- Récapitulatif des totaux de la fiche commande admin : une ligne sur deux grisée. --}}
<dl class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
    @foreach ($getState() ?? [] as $row)
        <div @class([
            'flex items-baseline justify-between gap-4 px-4 py-2.5 text-sm',
            'bg-gray-50 dark:bg-white/5' => $loop->even,
            'border-t border-gray-200 dark:border-white/10' => $row['emphasis'] ?? false,
        ])>
            <dt @class([
                'text-gray-950 dark:text-white',
                'font-semibold' => $row['emphasis'] ?? false,
                'font-medium' => ! ($row['emphasis'] ?? false),
            ])>{{ $row['label'] }}</dt>
            <dd @class([
                'whitespace-nowrap text-end tabular-nums',
                'font-bold text-gray-950 dark:text-white' => $row['emphasis'] ?? false,
                'font-semibold text-warning-600 dark:text-warning-400' => ($row['tone'] ?? null) === 'warning',
                'text-gray-700 dark:text-gray-300' => ! ($row['emphasis'] ?? false) && ($row['tone'] ?? null) === null,
            ])>{{ $row['value'] }}</dd>
        </div>
    @endforeach
</dl>
