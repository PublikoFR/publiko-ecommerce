@php
    /** @var \Pko\Pennylane\Support\InvoicePayload $payload */
    $payload = $getState();
    $lines = $payload->lines();
    $totals = $payload->totals();
    $currency = $payload->currency();
    $money = fn (int $cents): string => \Pko\Pennylane\Support\InvoicePayload::money($cents, $currency);
    $subject = $payload->get('pdf_invoice_subject');
@endphp

<div class="space-y-4">
    @if ($subject)
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $subject }}</p>
    @endif

    @if (empty($lines))
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            {{ __('pko-pennylane::admin.invoice.lines.empty') }}
        </div>
    @else
        <div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/5">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">{{ __('pko-pennylane::admin.invoice.lines.label') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pko-pennylane::admin.invoice.lines.quantity') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pko-pennylane::admin.invoice.lines.unit_price') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pko-pennylane::admin.invoice.lines.vat') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('pko-pennylane::admin.invoice.lines.total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($lines as $line)
                        <tr class="text-gray-950 dark:text-white">
                            <td class="px-4 py-3 font-medium">{{ $line['label'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ rtrim(rtrim(number_format($line['quantity'], 3, ',', ' '), '0'), ',') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $money($line['unit_cents']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ rtrim(rtrim(number_format($line['vat_rate'], 1, ',', ''), '0'), ',') }} %</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ $money($line['total_cents']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <dl class="ml-auto w-full max-w-xs space-y-2 text-sm">
            <div class="flex justify-between text-gray-500 dark:text-gray-400">
                <dt>{{ __('pko-pennylane::admin.invoice.lines.subtotal') }}</dt>
                <dd class="tabular-nums">{{ $money($totals['subtotal']) }}</dd>
            </div>
            <div class="flex justify-between text-gray-500 dark:text-gray-400">
                <dt>{{ __('pko-pennylane::admin.invoice.lines.tax') }}</dt>
                <dd class="tabular-nums">{{ $money($totals['tax']) }}</dd>
            </div>
            <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                <dt>{{ __('pko-pennylane::admin.invoice.lines.grand_total') }}</dt>
                <dd class="tabular-nums">{{ $money($totals['total']) }}</dd>
            </div>
        </dl>
    @endif
</div>
