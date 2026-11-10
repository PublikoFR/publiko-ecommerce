<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\AdminNav\Filament\Clusters\PkoSystemDataCluster;
use Pko\AiImporter\Enums\FlowState;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\RowFilter;
use Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;
use Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers\ImportLogsRelationManager;
use Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers\StagingRecordsRelationManager;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Support\ConfigColumnExtractor;
use Pko\AiImporter\Support\ConfigDiagnostics;
use Pko\AiImporter\Support\FilePreflight;
use Pko\AiImporter\Support\ProductFieldCatalog;

/**
 * Imports de produits — parcours en 4 étapes porté par 3 écrans :
 *
 *  - **Nouvel import** (`CreateImportJob`, assistant 2 étapes) : fichier +
 *    configuration avec contrôle immédiat, puis réglages de la préparation ;
 *  - **Un import** (`ViewImportJob`) : fil des étapes Préparation →
 *    Vérification → Import → Terminé, une seule action principale à la fois ;
 *  - **Liste** (`ListImportJobs`) : où en est chaque import.
 *
 * Les configurations de mapping vivent dans {@see ImporterConfigResource}.
 */
class ImportJobResource extends BaseResource
{
    protected static ?string $model = ImportJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    protected static ?int $navigationSort = 10;

    protected static ?string $cluster = PkoSystemDataCluster::class;

    /** Libellés affichés tels quels (pas de Majuscule À Chaque Mot dans les titres). */
    protected static bool $hasTitleCaseModelLabel = false;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getLabel(): string
    {
        return 'Import';
    }

    public static function getPluralLabel(): string
    {
        return 'Imports';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('ai-importer.navigation.group', 'Imports');
    }

    /**
     * Formulaire à plat (les deux étapes à la suite) : sert de repli ; la page
     * de création l'affiche en assistant via {@see self::fileStep()} et
     * {@see self::settingsStep()}.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Fichier')->schema(self::fileStep()),
            Forms\Components\Section::make('Réglages')->schema(self::settingsStep()),
        ]);
    }

    /**
     * Étape 1 — quel fichier, avec quelle configuration. Le contrôle (config
     * seule, puis fichier contre config) s'affiche dès que les deux sont là.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function fileStep(): array
    {
        return [
            Forms\Components\Select::make('config_id')
                ->label('Configuration')
                ->helperText('Le mapping à appliquer : quelles colonnes du fichier deviennent quels champs produit.')
                ->options(fn (): array => ImporterConfig::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('Choisir une configuration')
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    // Toutes les colonnes calculables sont cochées à chaque changement de config.
                    $set('options.columns_to_process', ConfigColumnExtractor::allColumnKeys(self::config($state)));
                })
                ->suffixAction(
                    Forms\Components\Actions\Action::make('editConfig')
                        ->label('Ouvrir la configuration')
                        ->icon('heroicon-m-pencil-square')
                        ->tooltip('Ouvrir la configuration')
                        ->visible(fn (Get $get): bool => filled($get('config_id')))
                        ->url(fn (Get $get): ?string => filled($get('config_id'))
                            ? ImporterConfigResource::getUrl('edit', ['record' => $get('config_id')])
                            : null, shouldOpenInNewTab: true),
                ),

            Forms\Components\FileUpload::make('input_file_path')
                ->label('Fichier')
                ->helperText('XLSX, XLS ou CSV. Un fichier FAB-DIS se dépose tel quel, avec toutes ses feuilles.')
                ->disk(config('ai-importer.storage.disk', 'local'))
                ->directory(config('ai-importer.storage.inputs_path', 'ai-importer/inputs'))
                ->acceptedFileTypes(config('ai-importer.upload.accepted_mimes'))
                ->maxSize(config('ai-importer.upload.max_size_kb'))
                // Nom lisible et unique : la liste et la fiche affichent le fichier d'origine.
                ->getUploadedFileNameForStorageUsing(static function (TemporaryUploadedFile $file): string {
                    $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'fichier';

                    return now()->format('Ymd-His').'-'.Str::limit($name, 80, '').'.'.strtolower($file->getClientOriginalExtension());
                })
                ->live()
                ->required(),

            Forms\Components\Placeholder::make('preflight')
                ->hiddenLabel()
                ->visible(fn (Get $get): bool => filled($get('config_id')))
                ->content(fn (Get $get): HtmlString => new HtmlString(view('pko-ai-importer::filament.preflight', [
                    'report' => self::preflight($get('config_id'), $get('input_file_path')),
                ])->render())),
        ];
    }

    /**
     * Étape 2 — ce que la préparation doit calculer, et sur quelles lignes.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function settingsStep(): array
    {
        return [
            Forms\Components\Section::make('Lignes à traiter')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('row_limit')
                        ->label('Nombre de lignes')
                        ->helperText('Vide = tout le fichier. Indiquez 10 ou 20 pour un essai avant de traiter le fichier entier.')
                        ->placeholder('Tout le fichier')
                        ->numeric()
                        ->minValue(1)
                        ->live(onBlur: true)
                        ->nullable(),

                    Forms\Components\Select::make('options.row_filter')
                        ->label('Produits concernés')
                        ->helperText('Les lignes écartées ne sont ni calculées (aucun appel IA) ni importées.')
                        ->options(collect(RowFilter::cases())->mapWithKeys(fn (RowFilter $f): array => [$f->value => $f->label()])->all())
                        ->default(RowFilter::All->value)
                        ->selectablePlaceholder(false)
                        ->live(),

                    Forms\Components\Select::make('options.join_column')
                        ->label('Reconnaître un produit existant par')
                        ->options(self::joinColumnOptions())
                        ->default('reference')
                        ->selectablePlaceholder(false)
                        ->visible(fn (Get $get): bool => ($get('options.row_filter') ?? RowFilter::All->value) !== RowFilter::All->value),
                ]),

            Forms\Components\Section::make('Colonnes à calculer')
                ->description('Décochez ce qui ne doit pas être recalculé. Une colonne décochée est absente des lignes préparées, donc non importée.')
                ->schema([
                    Forms\Components\CheckboxList::make('options.columns_to_process')
                        ->hiddenLabel()
                        ->options(fn (Get $get): array => self::columnCheckboxOptions($get('config_id')))
                        ->bulkToggleable()
                        ->columns(3)
                        ->gridDirection('row')
                        ->searchable()
                        ->allowHtml()
                        ->live()
                        ->hintActions([
                            Forms\Components\Actions\Action::make('deselectAiColumns')
                                ->label('Décocher les colonnes IA')
                                ->icon('heroicon-m-sparkles')
                                ->color('warning')
                                ->visible(fn (Get $get): bool => ConfigColumnExtractor::aiColumnKeys(self::config($get('config_id'))) !== [])
                                ->action(function (Get $get, Set $set): void {
                                    $ai = ConfigColumnExtractor::aiColumnKeys(self::config($get('config_id')));
                                    $set('options.columns_to_process', array_values(array_diff((array) ($get('options.columns_to_process') ?? []), $ai)));
                                }),
                        ]),

                    Forms\Components\Placeholder::make('columns_summary')
                        ->hiddenLabel()
                        ->content(fn (Get $get): HtmlString => new HtmlString(view('pko-ai-importer::filament.columns-summary', [
                            'summary' => self::columnsSummary($get('config_id'), (array) ($get('options.columns_to_process') ?? []), $get('row_limit'), $get('input_file_path')),
                        ])->render())),
                ]),

            Forms\Components\Section::make('Réglages avancés')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('error_policy')
                        ->label('En cas d\'erreur sur une ligne')
                        ->options(self::errorPolicyOptions())
                        ->default(config('ai-importer.defaults.error_policy', 'ignore'))
                        ->selectablePlaceholder(false)
                        ->required(),

                    Forms\Components\TextInput::make('chunk_size')
                        ->label('Taille du lot')
                        ->helperText('Lignes lues par lot sur un gros fichier. 500 convient dans presque tous les cas.')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(5000)
                        ->default(config('ai-importer.defaults.chunk_size', 500))
                        ->required(),

                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Importer automatiquement le')
                        ->helperText('Optionnel. Sans date, rien n\'est écrit dans le catalogue avant votre validation des lignes préparées.')
                        ->seconds(false)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<string, string> */
    public static function errorPolicyOptions(): array
    {
        return [
            'ignore' => 'Passer la ligne et continuer',
            'stop' => 'Arrêter le traitement',
            'rollback' => 'Arrêter et annuler tout l\'import',
        ];
    }

    /** @return array<string, string> */
    public static function joinColumnOptions(): array
    {
        return [
            'reference' => 'Référence (SKU, marque + référence fabricant)',
            'ean' => 'Code-barres EAN',
        ];
    }

    public static function config(mixed $id): ?ImporterConfig
    {
        return filled($id) ? ImporterConfig::query()->find($id) : null;
    }

    /**
     * Options de la grille « Colonnes à calculer » pour une config donnée :
     * `[clé_mapping => libellé HTML (avec badge IA)]`.
     *
     * @return array<string, HtmlString>
     */
    public static function columnCheckboxOptions(mixed $configId): array
    {
        $options = [];

        foreach (ConfigColumnExtractor::fromConfig(self::config($configId)) as $column) {
            $badge = $column['has_ai']
                ? ' <span class="fi-badge inline-flex items-center rounded-md bg-warning-50 px-1.5 py-0.5 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">IA</span>'
                : '';
            // Nom du champ en clair, source du fichier en retrait.
            $source = $column['source'] !== ''
                ? ' <span style="opacity:.55;font-size:.8125em;">'.e($column['source']).'</span>'
                : '';
            $options[$column['value']] = new HtmlString(e(ProductFieldCatalog::label($column['value'])).$badge.$source);
        }

        // Tri sur le libellé affiché.
        uasort($options, static fn (HtmlString $a, HtmlString $b): int => strcasecmp(
            Str::ascii(strip_tags((string) $a)),
            Str::ascii(strip_tags((string) $b)),
        ));

        return $options;
    }

    /**
     * Contrôle de l'étape 1 : la config seule, puis le fichier contre la config.
     * Mis en cache quelques minutes — l'assistant réévalue ses champs à chaque
     * interaction et la lecture du fichier n'a pas à être refaite.
     *
     * @return array{findings: array<int, array{level: string, message: string}>, sheets: array<string, int>, file: bool}
     */
    public static function preflight(mixed $configId, mixed $fileState): array
    {
        $config = self::config($configId);
        if ($config === null) {
            return ['findings' => [], 'sheets' => [], 'file' => false];
        }

        $data = ImporterConfigResource::arrayify($config->config_data);
        $path = self::uploadedFilePath($fileState);

        $key = 'ai-importer:preflight:'.md5($config->id.'|'.$config->updated_at.'|'.$path.'|'.($path !== null ? @filemtime($path) : ''));

        return Cache::remember($key, 300, function () use ($data, $path): array {
            // Les informations (colonnes non importées, colonnes IA) relèvent de
            // l'éditeur de configuration et de l'étape 2 : ici, seulement ce qui
            // compromet la préparation.
            $findings = array_values(array_filter(
                ConfigDiagnostics::analyze($data),
                static fn (array $finding): bool => $finding['level'] !== ConfigDiagnostics::INFO,
            ));
            $sheets = [];

            if ($path !== null) {
                $file = FilePreflight::check($path, $data);
                $sheets = $file['sheets'];
                $findings = [...$file['findings'], ...$findings];
            }

            return ['findings' => $findings, 'sheets' => $sheets, 'file' => $path !== null];
        });
    }

    /**
     * Chemin absolu du fichier déposé : fichier temporaire Livewire tant que le
     * formulaire n'est pas enregistré, fichier stocké ensuite.
     */
    public static function uploadedFilePath(mixed $state): ?string
    {
        $file = is_array($state) ? (array_values($state)[0] ?? null) : $state;

        if ($file instanceof TemporaryUploadedFile) {
            return $file->getRealPath() ?: null;
        }
        if (is_string($file) && $file !== '') {
            $disk = Storage::disk(config('ai-importer.storage.disk', 'local'));

            return $disk->exists($file) ? $disk->path($file) : null;
        }

        return null;
    }

    /**
     * Ce que la sélection de colonnes implique : colonnes calculées, colonnes
     * fixes toujours appliquées, nombre d'appels IA à prévoir.
     *
     * @param  array<int, string>  $selected
     * @return array{selected: int, total: int, ai: array<int, string>, static: array<string, string>, rows: int|null, calls: int|null}
     */
    public static function columnsSummary(mixed $configId, array $selected, mixed $rowLimit, mixed $fileState): array
    {
        $config = self::config($configId);
        $columns = ConfigColumnExtractor::fromConfig($config);
        $ai = array_values(array_intersect(ConfigColumnExtractor::aiColumnKeys($config), $selected));

        $rows = null;
        if ($config !== null) {
            $report = self::preflight($configId, $fileState);
            $primary = (string) (ImporterConfigResource::arrayify($config->config_data)['primary_sheet'] ?? '');
            $rows = $report['sheets'][$primary] ?? (array_values($report['sheets'])[0] ?? null);
        }
        if (is_numeric($rowLimit) && (int) $rowLimit > 0) {
            $rows = $rows === null ? (int) $rowLimit : min($rows, (int) $rowLimit);
        }

        return [
            'selected' => count(array_intersect(array_column($columns, 'value'), $selected)),
            'total' => count($columns),
            'ai' => $ai,
            // Seulement les valeurs fixes qui ont un effet à l'import.
            'static' => array_diff_key(
                ConfigColumnExtractor::staticColumns($config),
                array_flip(ConfigDiagnostics::ignoredKeys(array_keys(ConfigColumnExtractor::staticColumns($config)))),
            ),
            'rows' => $rows,
            'calls' => $rows !== null && $ai !== [] ? $rows * count($ai) : null,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('config'))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Import')
                    ->formatStateUsing(fn (ImportJob $record): string => '#'.$record->id.' · '.$record->displayName())
                    ->description(fn (ImportJob $record): ?string => $record->sourceFilename())
                    ->weight('medium')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('input_file_path', 'like', "%{$search}%")
                        ->orWhereHas('config', fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%")))
                    ->sortable(),
                Tables\Columns\TextColumn::make('flow')
                    ->label('Étape')
                    ->badge()
                    ->state(fn (ImportJob $record): string => $record->flowState()->label())
                    ->color(fn (ImportJob $record): string => $record->flowState()->color())
                    ->icon(fn (ImportJob $record): string => $record->flowState()->icon())
                    ->description(fn (ImportJob $record): ?string => self::flowDetail($record)),
                Tables\Columns\TextColumn::make('staging_count')
                    ->label('Lignes')
                    ->formatStateUsing(fn (ImportJob $record): string => number_format((int) $record->staging_count, 0, ',', ' ')
                        .($record->total_rows && $record->total_rows > $record->staging_count ? ' / '.number_format((int) $record->total_rows, 0, ',', ' ') : ''))
                    ->description(fn (ImportJob $record): ?string => $record->row_limit ? 'essai limité à '.$record->row_limit : null),
                Tables\Columns\TextColumn::make('imported_count')
                    ->label('Importées')
                    ->formatStateUsing(fn (ImportJob $record): string => $record->import_started_at === null ? '—' : number_format((int) $record->imported_count, 0, ',', ' '))
                    ->description(fn (ImportJob $record): ?string => $record->error_count > 0 ? $record->error_count.' erreur(s)' : null)
                    ->color(fn (ImportJob $record): ?string => $record->error_count > 0 ? 'danger' : null),
                Tables\Columns\TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('config_id')
                    ->label('Configuration')
                    ->relationship('config', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Préparation')
                    ->options(collect(JobStatus::cases())->mapWithKeys(fn (JobStatus $s): array => [$s->value => $s->label()])->all()),
                Tables\Filters\SelectFilter::make('import_status')
                    ->label('Import')
                    ->options(collect(ImportStatus::cases())->mapWithKeys(fn (ImportStatus $s): array => [$s->value => $s->label()])->all()),
            ])
            ->recordUrl(fn (ImportJob $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ouvrir')->icon('heroicon-m-arrow-right'),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading('Aucun import')
            ->emptyStateDescription('Déposez un fichier fournisseur pour préparer vos premiers produits.')
            ->emptyStateIcon('heroicon-o-arrow-down-on-square-stack')
            ->poll(fn (): ?string => ImportJob::query()
                ->whereIn('status', [JobStatus::Pending->value, JobStatus::Parsing->value])
                ->orWhereIn('import_status', [ImportStatus::Queued->value, ImportStatus::Importing->value])
                ->exists() ? '5s' : null)
            ->defaultSort('created_at', 'desc');
    }

    /** Précision sous le badge d'étape : avancement, échéance ou cause d'arrêt. */
    private static function flowDetail(ImportJob $record): ?string
    {
        return match ($record->flowState()) {
            FlowState::Preparing => $record->total_rows
                ? $record->processed_rows.' / '.$record->total_rows.' lignes'
                : null,
            FlowState::Scheduled => $record->scheduled_at?->isFuture()
                ? 'le '.$record->scheduled_at->format('d/m/Y à H:i')
                : 'démarrage imminent',
            FlowState::Importing => $record->staging_count
                ? $record->imported_count.' / '.$record->staging_count.' lignes'
                : null,
            FlowState::PrepareFailed, FlowState::ImportFailed => Str::limit((string) $record->error_message, 60) ?: null,
            default => null,
        };
    }

    public static function getRelations(): array
    {
        return [
            StagingRecordsRelationManager::class,
            ImportLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportJobs::route('/'),
            'create' => Pages\CreateImportJob::route('/create'),
            'view' => Pages\ViewImportJob::route('/{record}'),
        ];
    }
}
