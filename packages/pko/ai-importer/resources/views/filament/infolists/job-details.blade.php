{{-- Détails d'un import : fichier, configuration, réglages de préparation et
     d'import, sauvegardes. Replié par défaut. --}}
@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Storage;
    use Pko\AiImporter\Enums\RowFilter;
    use Pko\AiImporter\Enums\UpdateMode;
    use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
    use Pko\AiImporter\Filament\Resources\ImportJobResource;
    use Pko\AiImporter\Support\ProductFieldCatalog;

    /** @var \Pko\AiImporter\Models\ImportJob $record */
    $record = $getRecord();
    $options = (array) ($record->options ?? []);
    $disk = Storage::disk(config('ai-importer.storage.disk', 'local'));

    $human = static function (?int $bytes): string {
        if ($bytes === null) {
            return '';
        }
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return number_format($size, $i === 0 ? 0 : 1, ',', ' ').' '.$units[$i];
    };
    $fileSize = static fn (?string $path): ?int => $path && $disk->exists($path) ? $disk->size($path) : null;
    $labels = static fn (array $keys): string => collect($keys)->map(fn ($k): string => ProductFieldCatalog::label((string) $k))->implode(', ');

    $backupsDir = config('ai-importer.storage.backups_path', 'ai-importer/backups');
    $backups = collect($disk->exists($backupsDir) ? $disk->files($backupsDir) : [])
        ->filter(fn (string $p): bool => str_contains($p, 'job_'.$record->uuid.'_'))
        ->map(fn (string $p): array => [
            'name' => basename($p),
            'size' => $disk->size($p),
            // Horodatage du disque en UTC : affiché dans le fuseau de l'application.
            'date' => Carbon::createFromTimestamp($disk->lastModified($p))->setTimezone(config('app.timezone')),
            'active' => $p === $record->backup_path,
        ])
        ->sortByDesc('date')
        ->values();

    $computed = (array) ($options['columns_to_process'] ?? []);
    $written = (array) ($options['columns_to_import'] ?? []);
    $filter = RowFilter::tryFrom((string) ($options['row_filter'] ?? '')) ?? RowFilter::All;
    $mode = UpdateMode::tryFrom((string) ($options['update_mode'] ?? '')) ?? UpdateMode::All;
    $join = in_array((string) ($options['join_column'] ?? 'reference'), ['ean', 'ean13'], true) ? 'ean' : 'reference';
    $duration = static fn (?Carbon $from, ?Carbon $to): ?string => $from && $to ? $from->diffForHumans($to, ['syntax' => Carbon::DIFF_ABSOLUTE, 'parts' => 2]) : null;
@endphp
@include('pko-ai-importer::filament.partials.styles')
<div class="pko-imp">
    <dl class="pko-imp-dl">
        <dt>Configuration</dt>
        <dd>
            @if ($record->config)
                <a class="pko-imp-link" href="{{ ImporterConfigResource::getUrl('edit', ['record' => $record->config]) }}">{{ $record->config->name }}</a>
            @else
                Aucune <span class="pko-imp-muted">(fichier déjà préparé)</span>
            @endif
        </dd>

        <dt>Fichier source</dt>
        <dd>
            {{ $record->sourceFilename() ?? '—' }}
            @if ($size = $fileSize($record->input_file_path))
                <span class="pko-imp-muted">· {{ $human($size) }}</span>
            @elseif ($record->input_file_path)
                <span class="pko-imp-muted">· fichier supprimé du serveur</span>
            @endif
        </dd>

        <dt>Créé</dt>
        <dd>
            {{ $record->created_at?->format('d/m/Y à H:i') }}
            @if ($record->createdBy)
                <span class="pko-imp-muted">· par {{ trim(($record->createdBy->firstname ?? '').' '.($record->createdBy->lastname ?? '')) ?: $record->createdBy->email }}</span>
            @endif
        </dd>

        <dt>Préparation</dt>
        <dd>
            @if ($record->parse_started_at)
                démarrée le {{ $record->parse_started_at->format('d/m/Y à H:i') }}
                @if ($d = $duration($record->parse_started_at, $record->parse_completed_at))
                    <span class="pko-imp-muted">· durée {{ $d }}</span>
                @endif
            @else
                <span class="pko-imp-muted">{{ $record->config_id ? 'pas encore démarrée' : 'sans objet (fichier déjà préparé)' }}</span>
            @endif
        </dd>

        @if ($record->config_id)
            <dt>Lignes traitées</dt>
            <dd>
                {{ $record->row_limit ? 'essai sur les '.$record->row_limit.' premières lignes' : 'tout le fichier' }}
                <span class="pko-imp-muted">· {{ mb_strtolower($filter->label()) }}</span>
            </dd>

            <dt>Colonnes calculées</dt>
            <dd>{{ $computed === [] ? 'Toutes les colonnes de la configuration' : $labels($computed) }}</dd>
        @endif

        <dt>Produits déjà au catalogue</dt>
        <dd>
            {{ $mode->label() }}
            <span class="pko-imp-muted">· reconnus par {{ $join === 'ean' ? 'code-barres EAN' : 'référence' }}</span>
        </dd>

        <dt>Champs écrits</dt>
        <dd>{{ $written === [] ? 'Tous les champs préparés' : $labels($written) }}</dd>

        <dt>En cas d'erreur</dt>
        <dd>{{ ImportJobResource::errorPolicyOptions()[$record->error_policy->value] ?? $record->error_policy->value }}</dd>

        <dt>Import</dt>
        <dd>
            @if ($record->import_started_at)
                démarré le {{ $record->import_started_at->format('d/m/Y à H:i') }}
                @if ($d = $duration($record->import_started_at, $record->import_completed_at))
                    <span class="pko-imp-muted">· durée {{ $d }}</span>
                @endif
            @elseif ($record->scheduled_at)
                programmé le {{ $record->scheduled_at->format('d/m/Y à H:i') }}
            @else
                <span class="pko-imp-muted">pas encore lancé</span>
            @endif
        </dd>

        <dt>Sauvegarde</dt>
        <dd>
            @forelse ($backups as $backup)
                <div>
                    {{ $backup['name'] }}
                    <span class="pko-imp-muted">· {{ $human($backup['size']) }} · {{ $backup['date']->format('d/m/Y H:i') }}{{ $backup['active'] ? ' · utilisée pour annuler l\'import' : '' }}</span>
                </div>
            @empty
                <span class="pko-imp-muted">Prise automatiquement au démarrage de l'import.</span>
            @endforelse
        </dd>

        <dt>Identifiant technique</dt>
        <dd><code style="font-size:.8125rem;">{{ $record->uuid }}</code></dd>
    </dl>
</div>
