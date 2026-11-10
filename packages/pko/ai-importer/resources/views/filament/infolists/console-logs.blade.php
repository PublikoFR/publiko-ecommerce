{{-- Console d'une phase (préparation ou import), style terminal. Le
     rafraîchissement est porté par le bandeau d'étape (wire:poll de la page). --}}
@php
    /** @var \Pko\AiImporter\Models\ImportJob $record */
    $record = $getRecord();
    /** @var \Pko\AiImporter\Enums\LogPhase $phase Une console par phase : préparation (parse) ou import. */
    $phase ??= \Pko\AiImporter\Enums\LogPhase::Parse;
    $limit = 300;
    $query = \Pko\AiImporter\Filament\Resources\ImportJobResource\Pages\ViewImportJob::phaseLogs($record, $phase);
    $logs = (clone $query)->orderByDesc('id')->limit($limit)->get()->reverse();
    $running = $phase === \Pko\AiImporter\Enums\LogPhase::Import
        ? in_array($record->import_status->value, ['queued', 'importing'], true)
        : in_array($record->status->value, ['pending', 'parsing'], true);
    $palette = [
        'success' => ['#34d399', 'OK  '],
        'warning' => ['#fbbf24', 'WARN'],
        'error'   => ['#f87171', 'ERR '],
        'info'    => ['#60a5fa', 'INFO'],
        'debug'   => ['#9ca3af', 'DBG '],
    ];
@endphp
@include('pko-ai-importer::filament.partials.styles')
<div class="pko-imp-console">
    @if ($logs->count() >= $limit)
        <div style="color:#6b7280;">… {{ $limit }} dernières lignes — l'onglet « Journal détaillé » plus bas contient tout, avec filtres.</div>
    @endif
    @forelse ($logs as $log)
        @php([$color, $tag] = $palette[$log->level->value] ?? ['#e5e7eb', '----'])
        <div class="pko-imp-console-line">
            <span style="color:#6b7280;">{{ optional($log->created_at)->format('H:i:s') ?? '--:--:--' }}</span>
            <span style="color:{{ $color }};font-weight:600;">[{{ $tag }}]</span>
            @if ($log->row_number !== null)<span style="color:#818cf8;">#{{ $log->row_number }}</span>@endif
            <span class="pko-imp-console-msg">{{ $log->message }}</span>
        </div>
    @empty
        <div style="color:#6b7280;">{{ $phase === \Pko\AiImporter\Enums\LogPhase::Import ? 'L\'import n\'a pas encore démarré.' : 'La préparation n\'a pas encore démarré.' }}</div>
    @endforelse
    @if ($running)
        <div style="color:#34d399;margin-top:.25rem;">▌<span style="opacity:.6;"> traitement en cours…</span></div>
    @endif
</div>
