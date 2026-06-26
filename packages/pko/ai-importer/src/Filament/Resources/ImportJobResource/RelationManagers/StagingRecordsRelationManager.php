<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers;

use ArrayObject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Support\ProductFieldCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Aperçu des données » — preview & édition des lignes parsées avant import.
 *
 * UX hybride (décision 2026-06) :
 * - Colonnes dynamiques générées depuis les clés réelles du staging data
 *   (pas les colonnes statiques hardcodées), labellisées via ProductFieldCatalog.
 * - Double-clic sur une cellule → input Alpine inline → persiste via Livewire.
 * - Badge coloré (TextColumn) + SelectColumn adjacent pour l'édition inline du statut.
 * - Modal EditAction : formulaire champ par champ (pas le textarea JSON brut).
 */
class StagingRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'stagingRecords';

    protected static ?string $title = 'Aperçu des données';

    protected static ?string $icon = 'heroicon-o-table-cells';

    /** Cache des clés data calculées une fois par mount du composant. */
    private ?array $cachedDataKeys = null;

    // Édition autorisée même sur la page View (ViewImportJob) — override nécessaire
    // car Filament rend le RM en read-only par défaut sur une page ViewRecord.
    public function isReadOnly(): bool
    {
        return false;
    }

    // StagingRecord n'a pas de policy Shield (modèle interne). L'accès est gardé
    // par la policy du job parent ; on autorise explicitement edit/delete ici.
    protected function canEdit(Model $record): bool
    {
        return true;
    }

    protected function canDelete(Model $record): bool
    {
        return true;
    }

    protected function canDeleteAny(): bool
    {
        return true;
    }

    /**
     * Livewire action appelée par Alpine ($wire.updateCellValue) lors du double-clic.
     * Scope sur les staging records du job courant pour éviter toute manipulation
     * d'un enregistrement appartenant à un autre job.
     */
    public function updateCellValue(int $recordId, string $key, string $value): void
    {
        $record = $this->getOwnerRecord()->stagingRecords()->findOrFail($recordId);
        $data = (array) $record->data;
        $data[$key] = $value;
        $record->update(['data' => $data]);
    }

    public function form(Form $form): Form
    {
        $keys = $this->getDataKeys();
        $fieldLabels = ProductFieldCatalog::flat();

        $longTextKeys = ['description', 'description_short', 'meta_description', 'meta_keywords', 'features', 'collections', 'videos', 'images'];

        $dynamicFields = [];
        foreach ($keys as $key) {
            $label = $fieldLabels[$key] ?? $key;
            $component = in_array($key, $longTextKeys, true)
                ? Forms\Components\Textarea::make("data.{$key}")->label($label)->rows(3)
                : Forms\Components\TextInput::make("data.{$key}")->label($label);
            $dynamicFields[] = $component;
        }

        return $form->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Placeholder::make('log_history')
                        ->label('Historique des logs')
                        ->columnSpan(1)
                        ->content(fn (?StagingRecord $record): Htmlable => $this->renderRowLogHistory($record)),
                    Forms\Components\Group::make()
                        ->columnSpan(1)
                        ->schema(array_merge(
                            [
                                Forms\Components\TextInput::make('row_number')->label('Ligne #')->disabled(),
                                Forms\Components\Select::make('status')
                                    ->options(collect(StagingStatus::cases())
                                        ->mapWithKeys(fn (StagingStatus $s): array => [$s->value => $s->label()])
                                        ->toArray())
                                    ->required(),
                                Forms\Components\Textarea::make('error_message')->rows(2)->disabled(),
                            ],
                            $dynamicFields
                        )),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $keys = $this->getDataKeys();
        $fieldLabels = ProductFieldCatalog::flat();
        $hasImage = in_array('image', $keys, true) || in_array('images', $keys, true);

        $dynamicColumns = [];

        if ($hasImage) {
            $dynamicColumns[] = Tables\Columns\ImageColumn::make('data_image')
                ->label('Photo')
                ->state(fn (StagingRecord $r): ?string => $this->extractImageUrl($r))
                ->height(40)
                ->width(40);
        }

        foreach ($keys as $key) {
            if (in_array($key, ['image', 'images', 'videos'], true)) {
                continue;
            }
            $label = $fieldLabels[$key] ?? $key;
            $dynamicColumns[] = Tables\Columns\TextColumn::make("cell_{$key}")
                ->label($label)
                ->html()
                ->state(fn (StagingRecord $r): string => view(
                    'pko-ai-importer::filament.components.staging-cell',
                    [
                        'value' => ((array) $r->data)[$key] ?? '',
                        'recordId' => $r->id,
                        'key' => $key,
                    ]
                )->render());
        }

        $statusOptions = collect(StagingStatus::cases())
            ->mapWithKeys(fn (StagingStatus $s): array => [$s->value => $s->label()])
            ->toArray();

        return $table
            ->recordTitleAttribute('row_number')
            ->defaultSort('row_number')
            ->columns(array_merge(
                [
                    Tables\Columns\TextColumn::make('row_number')->label('#')->sortable(),
                ],
                $dynamicColumns,
                [
                    // Badge coloré (display) + SelectColumn adjacent (inline edit)
                    Tables\Columns\TextColumn::make('status_label')
                        ->label('Statut')
                        ->badge()
                        ->state(fn (StagingRecord $r): string => $r->status->label())
                        ->color(fn (StagingRecord $r): string => $r->status->color()),
                    Tables\Columns\SelectColumn::make('status')
                        ->label('Modifier')
                        ->options($statusOptions)
                        ->selectablePlaceholder(false)
                        ->rules(['required']),
                    Tables\Columns\TextColumn::make('error_message')
                        ->label('Erreur')
                        ->limit(60)
                        ->tooltip(fn ($record): ?string => $record->error_message),
                    Tables\Columns\TextColumn::make('lunar_product_id')
                        ->label('Produit Lunar')
                        ->placeholder('—'),
                ]
            ))
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options($statusOptions),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Exporter CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth(MaxWidth::FourExtraLarge)
                    ->modalHeading(fn (StagingRecord $record): string => 'Ligne #'.$record->row_number)
                    ->mutateFormDataUsing(function (array $data, StagingRecord $record): array {
                        // Merge back : on préserve les clés de data[] non représentées
                        // en tant que champs dans le formulaire (ex : imports sans config).
                        $existing = (array) $record->data;
                        $submitted = $data['data'] ?? [];
                        $data['data'] = array_merge($existing, $submitted);

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('validate')
                    ->label('Marquer validées')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['status' => StagingStatus::Validated, 'validated_at' => now()])),
                Tables\Actions\BulkAction::make('skip')
                    ->label('Ignorer')
                    ->icon('heroicon-o-forward')
                    ->action(fn ($records) => $records->each->update(['status' => StagingStatus::Skipped])),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Retourne les clés de data[] à exposer comme colonnes/champs de formulaire.
     *
     * Source primaire : les clés réelles du premier StagingRecord du job (pas le
     * mapping config), ce qui fonctionne pour les imports avec ET sans config
     * (ex : import CSV préparé sans ImporterConfig). Si une config est présente,
     * ses clés de mapping servent à ordonner les colonnes.
     *
     * @return array<int, string>
     */
    private function getDataKeys(): array
    {
        if ($this->cachedDataKeys !== null) {
            return $this->cachedDataKeys;
        }

        $job = $this->getOwnerRecord();
        $first = $job->stagingRecords()->orderBy('row_number')->first(['data']);

        if (! $first) {
            return $this->cachedDataKeys = [];
        }

        $dataKeys = array_keys((array) $first->data);

        $config = $job->config;
        if ($config) {
            $configData = $config->config_data;
            $mappingRaw = $configData instanceof ArrayObject
                ? ($configData->getArrayCopy()['mapping'] ?? [])
                : (is_array($configData) ? ($configData['mapping'] ?? []) : []);
            $mappingKeys = array_keys((array) $mappingRaw);
            // Config keys first (preserve display order), then any extra data keys
            $dataKeys = array_values(array_unique(array_merge(
                array_intersect($mappingKeys, $dataKeys),
                $dataKeys
            )));
        }

        return $this->cachedDataKeys = $dataKeys;
    }

    /**
     * Extrait la première URL d'image depuis data.image ou data.images.
     */
    private function extractImageUrl(StagingRecord $r): ?string
    {
        $data = (array) $r->data;

        if (! empty($data['image']) && is_string($data['image'])) {
            return $data['image'];
        }

        $images = $data['images'] ?? null;

        if (is_array($images) && ! empty($images)) {
            $first = reset($images);

            return is_string($first) ? $first : null;
        }

        if (is_string($images) && $images !== '') {
            $decoded = json_decode($images, true);
            if (is_array($decoded) && ! empty($decoded)) {
                $first = reset($decoded);

                return is_string($first) ? $first : null;
            }

            return $images;
        }

        return null;
    }

    private function renderRowLogHistory(?StagingRecord $record): Htmlable
    {
        if ($record === null) {
            return new HtmlString('<span class="text-gray-400">—</span>');
        }

        $logs = ImportLog::query()
            ->where('import_job_id', $record->import_job_id)
            ->where('row_number', $record->row_number)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        if ($logs->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">Aucun log pour cette ligne.</span>');
        }

        $colors = [
            LogLevel::Success->value => '#059669',
            LogLevel::Warning->value => '#d97706',
            LogLevel::Error->value => '#dc2626',
            LogLevel::Info->value => '#2563eb',
            LogLevel::Debug->value => '#6b7280',
        ];

        $rows = $logs->map(function (ImportLog $log) use ($colors): string {
            $color = $colors[$log->level->value] ?? '#374151';
            $time = optional($log->created_at)->format('H:i:s') ?? '';

            return sprintf(
                '<div style="font-family:ui-monospace,monospace;font-size:.75rem;"><span style="color:#9ca3af;">%s</span> <span style="color:%s;font-weight:600;">[%s]</span> %s</div>',
                e($time),
                $color,
                e(strtoupper($log->level->value)),
                e($log->message),
            );
        })->implode('');

        return new HtmlString('<div style="max-height:14rem;overflow-y:auto;">'.$rows.'</div>');
    }

    private function exportCsv(): StreamedResponse
    {
        $job = $this->getOwnerRecord();
        $filename = 'staging_'.$job->uuid.'.csv';

        $records = StagingRecord::query()
            ->where('import_job_id', $job->id)
            ->orderBy('row_number')
            ->get();

        $keys = $records
            ->flatMap(fn (StagingRecord $r): array => array_keys((array) $r->data))
            ->unique()
            ->values()
            ->all();

        return response()->streamDownload(function () use ($records, $keys): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, array_merge(['row_number', 'status', 'error_message'], $keys));
            foreach ($records as $record) {
                $data = (array) $record->data;
                $line = [$record->row_number, $record->status->value, $record->error_message];
                foreach ($keys as $key) {
                    $value = $data[$key] ?? '';
                    $line[] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
