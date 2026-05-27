{{-- Panneau « Logs de console » style terminal — portage de la console PrestaShop.
     Rafraîchi par Livewire (wire:poll) tant que l'import est en cours. --}}
@php
    /** @var \Pko\AiImporter\Models\ImportJob $record */
    $record = $getRecord();

    $logs = $record->logs()
        ->orderByDesc('id')
        ->limit(300)
        ->get()
        ->reverse();

    $running = in_array($record->import_status->value, ['importing'], true)
        || in_array($record->status->value, ['pending', 'parsing'], true);

    $palette = [
        'success' => ['#34d399', 'OK  '],
        'warning' => ['#fbbf24', 'WARN'],
        'error'   => ['#f87171', 'ERR '],
        'info'    => ['#60a5fa', 'INFO'],
        'debug'   => ['#9ca3af', 'DBG '],
    ];
@endphp

<div
    @if ($running) wire:poll.3s @endif
    style="background:#0b1020;border:1px solid #1f2937;border-radius:.5rem;padding:1rem;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.8rem;line-height:1.5;max-height:28rem;overflow-y:auto;"
>
    @forelse ($logs as $log)
        @php([$color, $tag] = $palette[$log->level->value] ?? ['#e5e7eb', '----'])
        <div style="white-space:pre-wrap;color:#e5e7eb;">
            <span style="color:#6b7280;">{{ optional($log->created_at)->format('H:i:s') ?? '--:--:--' }}</span>
            <span style="color:{{ $color }};font-weight:600;"> [{{ $tag }}]</span>
            @if ($log->row_number !== null)<span style="color:#818cf8;"> #{{ $log->row_number }}</span>@endif
            <span> {{ $log->message }}</span>
        </div>
    @empty
        <div style="color:#6b7280;">Aucun log pour ce job pour l'instant.</div>
    @endforelse

    @if ($running)
        <div style="color:#34d399;">▌<span style="opacity:.6;"> import en cours…</span></div>
    @endif
</div>
