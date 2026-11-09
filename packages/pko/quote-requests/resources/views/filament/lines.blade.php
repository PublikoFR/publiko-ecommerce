@php $lines = $getRecord()->lines ?? []; @endphp
@if ($lines === [])
    <p class="text-sm text-gray-500">—</p>
@else
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-gray-500">
                <th class="py-1 pr-4 font-medium">Référence / désignation</th>
                <th class="py-1 font-medium text-right">Quantité</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($lines as $line)
                <tr>
                    <td class="py-1.5 pr-4">{{ $line['label'] ?? '' }}</td>
                    <td class="py-1.5 text-right font-mono">{{ $line['quantity'] ?? 1 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
