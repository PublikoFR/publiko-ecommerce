{{-- Bandeau d'un import : fil des 4 étapes, état courant en clair (ce qu'il se
     passe, quoi faire), progression et compteurs de lignes. Se rafraîchit seul
     tant qu'un traitement tourne. --}}
@php
    use Pko\AiImporter\Enums\FlowState;
    use Pko\AiImporter\Services\ProgressCache;

    /** @var \Pko\AiImporter\Models\ImportJob $record */
    $record = $getRecord();
    $state = $record->flowState();
    $step = $state->step();
    $counts = $record->stagingStatusCounts();
    $fmt = static fn (?int $n): string => $n === null ? '?' : number_format($n, 0, ',', ' ');
    $plural = static fn (int $n, string $one, string $many): string => $fmt($n).' '.($n > 1 ? $many : $one);

    // Progression : cache (rafraîchi par les jobs) puis compteurs en base.
    $progress = null;
    if ($state === FlowState::Preparing) {
        $cached = ProgressCache::get($record);
        $done = (int) ($cached['processed'] ?? $record->processed_rows);
        $total = $record->row_limit ? min((int) $record->row_limit, (int) ($record->total_rows ?: $record->row_limit)) : $record->total_rows;
        $progress = ['done' => $done, 'total' => $total, 'unit' => 'lignes préparées'];
    } elseif ($state === FlowState::Importing) {
        $done = $counts['imported'] + $counts['error'];
        $total = $done + $counts['pending'] + $counts['warning'];
        $progress = ['done' => $done, 'total' => $total, 'unit' => 'lignes importées'];
    }
    $pct = $progress && $progress['total'] ? (int) min(100, round($progress['done'] / $progress['total'] * 100)) : null;

    $ready = $counts['pending'] + $counts['warning'];

    [$title, $text] = match ($state) {
        FlowState::Waiting => [
            'Préparation en file d\'attente',
            'Le fichier est pris en charge dans les 2 minutes. Vous pouvez quitter cette page : la préparation continue sans vous.',
        ],
        FlowState::Preparing => [
            'Préparation en cours'.($pct !== null ? ' · '.$pct.' %' : ''),
            'Chaque ligne du fichier est transformée selon la configuration. Les lignes apparaissent ci-dessous au fur et à mesure ; rien n\'est écrit dans le catalogue.',
        ],
        FlowState::PrepareFailed => [
            'La préparation s\'est arrêtée',
            'Corrigez la cause ci-dessous, puis relancez : la préparation reprend après la dernière ligne traitée.',
        ],
        FlowState::Cancelled => [
            'Préparation arrêtée',
            $counts['total'] > 0
                ? $plural($counts['total'], 'ligne déjà préparée reste consultable', 'lignes déjà préparées restent consultables').'. Vous pouvez relancer la préparation là où elle s\'est arrêtée.'
                : 'Aucune ligne n\'a été préparée. Vous pouvez relancer la préparation.',
        ],
        FlowState::Review => [
            $ready > 0 ? $plural($ready, 'ligne à vérifier', 'lignes à vérifier') : 'Aucune ligne à importer',
            $ready > 0
                ? 'Contrôlez les lignes ci-dessous. Cliquez sur une ligne pour la corriger, écartez celles à ne pas importer, puis lancez l\'import. Rien n\'a encore été écrit dans le catalogue.'
                : 'Toutes les lignes sont écartées ou en erreur. Rétablissez-en au moins une pour pouvoir importer.',
        ],
        FlowState::Scheduled => $record->scheduled_at?->isFuture()
            ? [
                'Import programmé le '.$record->scheduled_at->format('d/m/Y à H:i'),
                $plural($ready, 'ligne sera importée', 'lignes seront importées').' automatiquement à cette date. Vous pouvez encore corriger les lignes d\'ici là.',
            ]
            : [
                'Import sur le point de démarrer',
                'Il démarre dans les 2 minutes. Une sauvegarde des produits concernés est prise avant la première écriture.',
            ],
        FlowState::Importing => [
            'Import en cours'.($pct !== null ? ' · '.$pct.' %' : ''),
            'Les produits sont créés ou mis à jour dans le catalogue. Vous pouvez quitter cette page : l\'import continue sans vous.',
        ],
        FlowState::ImportFailed => [
            'L\'import s\'est arrêté',
            'Les lignes déjà importées le restent. Vous pouvez reprendre l\'import là où il s\'est arrêté, ou l\'annuler pour revenir à l\'état d\'avant.',
        ],
        FlowState::Done => [
            $counts['error'] > 0 ? 'Import terminé avec '.$plural($counts['error'], 'erreur', 'erreurs') : 'Import terminé',
            trim(
                $plural($counts['created'], 'produit créé', 'produits créés').', '.$plural($counts['updated'], 'mis à jour', 'mis à jour').'.'
                .($counts['error'] > 0 ? ' Ouvrez l\'onglet « Erreurs » pour voir la cause de chaque ligne rejetée, corrigez-la puis réessayez.' : '')
                .($ready > 0 ? ' '.$plural($ready, 'ligne reste', 'lignes restent').' à importer.' : '')
            ),
        ],
        FlowState::RolledBack => [
            'Import annulé',
            'Le catalogue est revenu à son état d\'avant l\'import. Les lignes sont de nouveau à vérifier : vous pouvez les corriger et relancer l\'import.',
        ],
    };

    $tone = match ($state->color()) {
        'info' => 'info', 'warning' => 'warning', 'danger' => 'danger', 'success' => 'success', default => 'gray',
    };
    if ($state === FlowState::Done && $counts['error'] > 0) {
        $tone = 'warning';
    }
    $spins = in_array($state, [FlowState::Preparing, FlowState::Importing], true);

    // Alerte avant import : plusieurs lignes pour une même référence.
    $duplicates = in_array($state, [FlowState::Review, FlowState::Scheduled, FlowState::RolledBack], true)
        ? $record->duplicateReferences()
        : [];

    $counters = array_filter([
        ['label' => 'ligne(s) préparée(s)', 'value' => $counts['total'], 'tone' => null, 'always' => true],
        ['label' => 'à importer', 'value' => $ready, 'tone' => null],
        ['label' => 'créé(s)', 'value' => $counts['created'], 'tone' => 'success'],
        ['label' => 'mis à jour', 'value' => $counts['updated'], 'tone' => 'success'],
        ['label' => 'écartée(s)', 'value' => $counts['skipped'], 'tone' => null],
        ['label' => 'en erreur', 'value' => $counts['error'], 'tone' => 'danger'],
    ], fn (array $c): bool => ($c['always'] ?? false) || $c['value'] > 0);
@endphp
@include('pko-ai-importer::filament.partials.styles')
<div class="pko-imp" @if ($state->isRunning()) wire:poll.3s="refreshFlow" @endif>
    <ol class="pko-imp-steps" aria-label="Étapes de l'import">
        @foreach (FlowState::steps() as $number => $label)
            @php
                $done = $number < $step || $state === FlowState::Done;
                $current = $number === $step && $state !== FlowState::Done;
            @endphp
            <li class="pko-imp-step {{ $done ? 'pko-imp-step--done' : '' }} {{ $current ? 'pko-imp-step--current' : '' }}" @if ($current) aria-current="step" @endif>
                <span class="pko-imp-step-dot">
                    @if ($done)
                        <x-filament::icon icon="heroicon-m-check" />
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span class="pko-imp-step-label">{{ $label }}</span>
            </li>
            @unless ($loop->last)
                <li class="pko-imp-step-line {{ $number < $step || $state === FlowState::Done ? 'pko-imp-step-line--done' : '' }}" aria-hidden="true"></li>
            @endunless
        @endforeach
    </ol>

    <div class="pko-imp-state">
        <span class="pko-imp-state-icon pko-imp-state-icon--{{ $tone }} {{ $spins ? 'pko-imp-state-icon--spin' : '' }}">
            <x-filament::icon :icon="$state->icon()" />
        </span>
        <div style="flex:1;min-width:0;">
            <div class="pko-imp-state-title">{{ $title }}</div>
            <div class="pko-imp-state-text">{{ $text }}</div>

            @if ($state->isFailure() && filled($record->error_message))
                <div class="pko-imp-error">{{ $record->error_message }}</div>
            @endif

            @if ($duplicates !== [])
                <div class="pko-imp-finding pko-imp-finding--warning" style="margin-top:.75rem;">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" />
                    <span>
                        Plusieurs lignes portent la même référence :
                        @foreach ($duplicates as $reference => $count)
                            « {{ $reference }} » ({{ $fmt($count) }} lignes){{ $loop->last ? '.' : ',' }}
                        @endforeach
                        Elles viseraient le même produit, chacune écrasant la précédente. Vérifiez la colonne de référence dans la configuration, ou écartez les lignes en trop.
                    </span>
                </div>
            @endif

            @if ($progress !== null)
                <div class="pko-imp-progress">
                    <div class="pko-imp-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct ?? 0 }}">
                        <div class="pko-imp-progress-fill" style="width: {{ $pct ?? 3 }}%;"></div>
                    </div>
                    <div class="pko-imp-progress-legend">
                        <span>{{ $fmt($progress['done']) }} / {{ $fmt($progress['total']) }} {{ $progress['unit'] }}</span>
                        @if ($pct !== null)<span>{{ $pct }} %</span>@endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="pko-imp-counters">
        @foreach ($counters as $counter)
            <div class="pko-imp-counter {{ $counter['tone'] ? 'pko-imp-counter--'.$counter['tone'] : '' }}">
                <b>{{ $fmt($counter['value']) }}</b><span>{{ $counter['label'] }}</span>
            </div>
        @endforeach
        @if ($record->row_limit)
            <div class="pko-imp-counter"><span>essai limité à {{ $fmt((int) $record->row_limit) }} ligne(s){{ $record->total_rows ? ' sur '.$fmt((int) $record->total_rows) : '' }}</span></div>
        @endif
    </div>
</div>
