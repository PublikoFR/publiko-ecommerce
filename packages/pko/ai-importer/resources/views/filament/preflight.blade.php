{{-- Étape 1 d'un nouvel import : contrôle de la configuration, puis du fichier
     contre la configuration, avant tout traitement. --}}
@php
    /** @var array{findings: array<int, array{level: string, message: string}>, sheets: array<string, int>, file: bool} $report */
    $findings = $report['findings'];
    $hasError = collect($findings)->contains(fn (array $f): bool => $f['level'] === 'error');
    $hasWarning = collect($findings)->contains(fn (array $f): bool => $f['level'] === 'warning');
    if ($report['file'] && ! $hasError && ! $hasWarning) {
        array_unshift($findings, ['level' => 'success', 'message' => 'Le fichier correspond à la configuration : feuilles et colonnes attendues trouvées.']);
    }
@endphp
@include('pko-ai-importer::filament.partials.styles')
<div class="pko-imp pko-imp-card">
    <div class="pko-imp-card-title">
        {{ $report['file'] ? 'Contrôle du fichier' : 'Contrôle de la configuration' }}
    </div>

    @if ($report['sheets'] !== [])
        <div class="pko-imp-chips">
            @foreach ($report['sheets'] as $name => $rows)
                <span class="pko-imp-chip"><strong>{{ $name }}</strong> {{ number_format($rows, 0, ',', ' ') }} ligne{{ $rows > 1 ? 's' : '' }}</span>
            @endforeach
        </div>
    @endif

    @if ($findings !== [])
        @include('pko-ai-importer::filament.partials.findings', ['findings' => $findings])
    @endif

    @unless ($report['file'])
        <p class="pko-imp-muted" style="margin-top:.5rem;">Déposez le fichier : ses feuilles et ses colonnes seront comparées à la configuration avant de continuer.</p>
    @endunless
</div>
