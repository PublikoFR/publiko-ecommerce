{{-- « Fichiers joints » — fichier source, fichier traité, sauvegardes (snapshots Lunar).
     Le téléchargement/restauration de la sauvegarde active passe par les actions de section. --}}
@php
    /** @var \Pko\AiImporter\Models\ImportJob $record */
    $record = $getRecord();
    $disk = \Illuminate\Support\Facades\Storage::disk(config('ai-importer.storage.disk', 'local'));

    $describe = function (?string $path) use ($disk): array {
        if (! $path || ! $disk->exists($path)) {
            return ['name' => $path ? basename($path) : null, 'exists' => false, 'size' => null, 'date' => null];
        }

        return [
            'name' => basename($path),
            'exists' => true,
            'size' => $disk->size($path),
            'date' => \Illuminate\Support\Carbon::createFromTimestamp($disk->lastModified($path)),
        ];
    };

    $human = static function (?int $bytes): string {
        if ($bytes === null) {
            return '—';
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

    $source = $describe($record->input_file_path);
    $processed = $describe($record->output_file_path);

    $backupsDir = config('ai-importer.storage.backups_path', 'ai-importer/backups');
    $backups = collect($disk->exists($backupsDir) ? $disk->files($backupsDir) : [])
        ->filter(fn (string $p): bool => str_contains($p, 'job_'.$record->uuid.'_'))
        ->map(fn (string $p): array => $describe($p) + ['active' => $p === $record->backup_path])
        ->sortByDesc('date')
        ->values();
@endphp

<div class="fi-section-content-ctn">
    <table class="w-full text-sm">
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            <tr>
                <td class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Fichier source</td>
                <td class="py-2 text-gray-950 dark:text-white">
                    {{ $source['name'] ?? '—' }}
                    @if ($source['exists'])
                        <span class="text-gray-400">· {{ $human($source['size']) }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Fichier traité</td>
                <td class="py-2 text-gray-950 dark:text-white">
                    {{ $processed['name'] ?? '—' }}
                    @if ($processed['exists'])
                        <span class="text-gray-400">· {{ $human($processed['size']) }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="py-2 pr-4 font-medium text-gray-500 dark:text-gray-400 align-top whitespace-nowrap">Sauvegardes</td>
                <td class="py-2 text-gray-950 dark:text-white">
                    @forelse ($backups as $backup)
                        <div class="flex items-center gap-2">
                            <span>{{ $backup['name'] }}</span>
                            <span class="text-gray-400">· {{ $human($backup['size']) }} · {{ optional($backup['date'])->format('d/m/Y H:i') }}</span>
                            @if ($backup['active'])
                                <span class="fi-badge inline-flex items-center rounded-md bg-primary-50 px-1.5 py-0.5 text-xs font-medium text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">active</span>
                            @endif
                        </div>
                    @empty
                        <span class="text-gray-400">Aucune sauvegarde.</span>
                    @endforelse
                </td>
            </tr>
        </tbody>
    </table>
</div>
