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
use Illuminate\Support\HtmlString;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\AdminNav\Filament\Clusters\PkoSystemDataCluster;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\RowFilter;
use Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;
use Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers\ImportLogsRelationManager;
use Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers\StagingRecordsRelationManager;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Support\ConfigColumnExtractor;

class ImportJobResource extends BaseResource
{
    protected static ?string $model = ImportJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    protected static ?int $navigationSort = 10;

    protected static ?string $cluster = PkoSystemDataCluster::class;

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

    public static function form(Form $form): Form
    {
        return $form->schema([
            // 1. Configurations enregistrées (inline, fidèle au panneau PS) —
            //    table + actions éditer/dupliquer/supprimer + import JSON via
            //    les header actions de la page CreateImportJob.
            Forms\Components\Section::make('Configurations enregistrées')
                ->icon('heroicon-o-folder-open')
                ->description('Gérez vos configurations de mapping. Le bouton « Nouvelle configuration » et l\'import JSON sont dans la barre d\'actions ci-dessus.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\View::make('pko-ai-importer::filament.saved-configs'),
                ]),

            // 2. Préparer un fichier — config + colonnes + fichier.
            Forms\Components\Section::make('Préparer un fichier')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    Forms\Components\Select::make('config_id')
                        ->label('Configuration')
                        ->relationship('config', 'name')
                        ->placeholder('-- Sélectionnez une configuration --')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            // Pré-coche toutes les colonnes traitables à chaque
                            // changement de config (parité PS).
                            $config = $state ? ImporterConfig::find($state) : null;
                            $set('options.columns_to_process', ConfigColumnExtractor::allColumnKeys($config));
                        }),

                    // 3. Colonnes à traiter — grille de cases générée du mapping.
                    Forms\Components\CheckboxList::make('options.columns_to_process')
                        ->label('Colonnes à traiter')
                        ->helperText('Décochez les colonnes que vous ne souhaitez pas traiter. Les colonnes avec un badge « AI Prompt » déclenchent un appel LLM.')
                        ->options(fn (Get $get): array => self::columnCheckboxOptions($get('config_id')))
                        ->bulkToggleable()
                        ->columns(2)
                        ->gridDirection('row')
                        ->searchable()
                        ->allowHtml()
                        ->visible(fn (Get $get): bool => self::columnCheckboxOptions($get('config_id')) !== [])
                        ->hintActions([
                            Forms\Components\Actions\Action::make('deselectAiColumns')
                                ->label('Désélectionner les colonnes IA')
                                ->icon('heroicon-m-sparkles')
                                ->color('warning')
                                ->visible(fn (Get $get): bool => ConfigColumnExtractor::aiColumnKeys(
                                    $get('config_id') ? ImporterConfig::find($get('config_id')) : null
                                ) !== [])
                                ->action(function (Get $get, Set $set): void {
                                    $ai = ConfigColumnExtractor::aiColumnKeys(
                                        $get('config_id') ? ImporterConfig::find($get('config_id')) : null
                                    );
                                    $current = (array) ($get('options.columns_to_process') ?? []);
                                    $set('options.columns_to_process', array_values(array_diff($current, $ai)));
                                }),
                        ]),

                    Forms\Components\FileUpload::make('input_file_path')
                        ->label('Fichier à importer')
                        ->helperText('Formats acceptés : XLSX, XLS, CSV.')
                        ->disk(config('ai-importer.storage.disk', 'local'))
                        ->directory(config('ai-importer.storage.inputs_path', 'ai-importer/inputs'))
                        ->acceptedFileTypes(config('ai-importer.upload.accepted_mimes'))
                        ->maxSize(config('ai-importer.upload.max_size_kb'))
                        ->required(),
                ]),

            // 4-5. Options avancées — filtrage, limite, lot, erreur, planif.
            Forms\Components\Section::make('Options avancées')
                ->icon('heroicon-o-cog-6-tooth')
                ->collapsible()
                ->columns(2)
                ->schema([
                    Forms\Components\Radio::make('options.row_filter')
                        ->label('Filtrage des lignes')
                        ->helperText('Filtre les produits selon leur présence dans la boutique.')
                        ->options(collect(RowFilter::cases())->mapWithKeys(fn (RowFilter $f) => [$f->value => $f->label()]))
                        ->default(RowFilter::All->value)
                        ->live()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('options.join_column')
                        ->label('Colonne de référence')
                        ->helperText('Colonne d\'identification du produit existant. Mappez votre réf. fournisseur sur la référence (SKU).')
                        ->options([
                            'reference' => 'Référence (SKU)',
                            'ean' => 'EAN / code-barres',
                        ])
                        ->default('reference')
                        ->visible(fn (Get $get): bool => in_array(
                            $get('options.row_filter'),
                            [RowFilter::MissingSupplierRef->value, RowFilter::ExistingSupplierRef->value],
                            true,
                        ))
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('row_limit')
                        ->label('Limite de lignes')
                        ->helperText('Laissez vide pour traiter tout le fichier (utile pour tester).')
                        ->numeric()
                        ->minValue(1)
                        ->nullable(),

                    Forms\Components\TextInput::make('chunk_size')
                        ->label('Taille du lot')
                        ->helperText('Nombre de lignes traitées par lot (recommandé : 500).')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(5000)
                        ->default(config('ai-importer.defaults.chunk_size', 500))
                        ->required(),

                    Forms\Components\Select::make('error_policy')
                        ->label('Politique d\'erreur')
                        ->options([
                            'ignore' => 'Ignorer et continuer',
                            'stop' => 'Arrêter l\'import',
                            'rollback' => 'Rollback complet',
                        ])
                        ->default(config('ai-importer.defaults.error_policy', 'ignore'))
                        ->required(),

                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Programmer l\'import')
                        ->helperText('Optionnel : l\'import Lunar sera lancé automatiquement à cette date (commande ai-importer:run-scheduled).'),
                ]),
        ]);
    }

    /**
     * Options de la grille « Colonnes à traiter » pour une config donnée :
     * `[clé_mapping => libellé HTML (avec badge IA)]`.
     *
     * @return array<string, HtmlString>
     */
    public static function columnCheckboxOptions(?string $configId): array
    {
        $config = $configId ? ImporterConfig::find($configId) : null;
        $options = [];

        foreach (ConfigColumnExtractor::fromConfig($config) as $column) {
            $badge = $column['has_ai']
                ? ' <span class="fi-badge inline-flex items-center rounded-md bg-warning-50 px-1.5 py-0.5 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">AI Prompt</span>'
                : '';
            $options[$column['value']] = new HtmlString(e($column['label']).$badge);
        }

        return $options;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')->label('Job')->copyable()->limit(8)->fontFamily('mono'),
                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->state(fn (ImportJob $record): string => $record->config_id ? 'Préparation' : 'Import CSV')
                    ->color(fn (string $state): string => $state === 'Préparation' ? 'primary' : 'success')
                    ->icon(fn (string $state): string => $state === 'Préparation' ? 'heroicon-m-cog-6-tooth' : 'heroicon-m-arrow-up-tray'),
                Tables\Columns\TextColumn::make('config.name')->label('Configuration')->placeholder('—'),
                Tables\Columns\TextColumn::make('input_file_path')
                    ->label('Fichier')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : '—')
                    ->limit(28)
                    ->tooltip(fn (?string $state): ?string => $state ? basename($state) : null),
                Tables\Columns\TextColumn::make('status')
                    ->label('Préparation')
                    ->badge()
                    ->color(fn (JobStatus $state): string => $state->color())
                    ->formatStateUsing(fn (JobStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('import_status')
                    ->label('Import')
                    ->badge()
                    ->color(fn (ImportStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ImportStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('processed_rows')
                    ->label('Progression')
                    ->formatStateUsing(fn ($state, ImportJob $record): string => $state.' / '.($record->total_rows ?? '?')
                        .($record->row_limit ? ' (max '.$record->row_limit.')' : '')),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d/m/Y H:i')->label('Date')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(JobStatus::cases())->mapWithKeys(fn (JobStatus $s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('import_status')
                    ->options(collect(ImportStatus::cases())->mapWithKeys(fn (ImportStatus $s) => [$s->value => $s->label()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Voir')->iconButton()->icon('heroicon-o-eye'),
                Tables\Actions\Action::make('logs')
                    ->label('Logs')
                    ->iconButton()
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->url(fn (ImportJob $record): string => static::getUrl('view', ['record' => $record]).'#relation-manager'),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->defaultSort('created_at', 'desc');
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
