{{-- Étape 2 d'un nouvel import : ce que la sélection de colonnes implique. --}}
@php
    /** @var array{selected: int, total: int, ai: array<int, string>, static: array<string, string>, rows: int|null, calls: int|null} $summary */
    $labels = \Pko\AiImporter\Support\ProductFieldCatalog::class;
    $findings = [];

    if ($summary['total'] > 0 && $summary['selected'] === 0) {
        $findings[] = ['level' => 'error', 'message' => 'Aucune colonne cochée : la préparation ne calculerait rien.'];
    }

    if ($summary['ai'] !== []) {
        $names = collect($summary['ai'])->map(fn (string $k): string => $labels::label($k))->implode(', ');
        $findings[] = [
            'level' => 'warning',
            'message' => $summary['calls'] !== null
                ? sprintf(
                    '%d colonne(s) calculée(s) par IA (%s) × %s ligne(s) = jusqu\'à %s appels facturés. Faites un essai sur quelques lignes avant le fichier entier.',
                    count($summary['ai']),
                    $names,
                    number_format($summary['rows'], 0, ',', ' '),
                    number_format($summary['calls'], 0, ',', ' '),
                )
                : sprintf('%d colonne(s) calculée(s) par IA (%s) : un appel facturé par ligne et par colonne.', count($summary['ai']), $names),
        ];
    } elseif ($summary['total'] > 0) {
        $findings[] = ['level' => 'success', 'message' => 'Aucune colonne IA cochée : la préparation ne déclenche aucun appel facturé.'];
    }

    if ($summary['static'] !== []) {
        $fixed = collect($summary['static'])
            ->map(fn (string $value, string $key): string => $labels::label($key).' = '.($value === '' ? '(vide)' : \Illuminate\Support\Str::limit($value, 24)))
            ->implode(' · ');
        $findings[] = ['level' => 'info', 'message' => 'Valeurs fixes de la configuration, toujours appliquées : '.$fixed.'.'];
    }
@endphp
@include('pko-ai-importer::filament.partials.styles')
@if ($findings !== [])
    <div class="pko-imp">
        @include('pko-ai-importer::filament.partials.findings', ['findings' => $findings])
    </div>
@endif
