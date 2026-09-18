{{-- Rappel des documents Pennylane dans le bloc « Transactions » de la fiche admin. --}}
@php($data = $getState() ?? [])

<div class="divide-y divide-gray-100 rounded-lg border border-gray-200 px-4 dark:divide-white/10 dark:border-white/10">
    @foreach (array_filter([$data['invoice'] ?? null, ...($data['credit_notes'] ?? [])]) as $document)
        @include('filament.orders.partials.document-row', ['document' => $document, 'class' => 'py-3'])
    @endforeach
</div>
