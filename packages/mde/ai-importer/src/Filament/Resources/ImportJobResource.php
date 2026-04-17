<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Mde\AiImporter\Enums\ImportStatus;
use Mde\AiImporter\Enums\JobStatus;
use Mde\AiImporter\Filament\Resources\ImportJobResource\Pages;
use Mde\AiImporter\Models\ImportJob;

class ImportJobResource extends BaseResource
{
    protected static ?string $model = ImportJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    protected static ?int $navigationSort = 10;

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
            Forms\Components\Select::make('config_id')
                ->label('Configuration')
                ->relationship('config', 'name')
                ->required(),
            Forms\Components\FileUpload::make('input_file_path')
                ->label('Fichier')
                ->disk(config('ai-importer.storage.disk', 'local'))
                ->directory(config('ai-importer.storage.inputs_path', 'ai-importer/inputs'))
                ->acceptedFileTypes(config('ai-importer.upload.accepted_mimes'))
                ->maxSize(config('ai-importer.upload.max_size_kb'))
                ->required(),
            Forms\Components\TextInput::make('chunk_size')
                ->label('Taille chunk')
                ->numeric()
                ->default(config('ai-importer.defaults.chunk_size', 500))
                ->required(),
            Forms\Components\TextInput::make('row_limit')
                ->label('Limite de lignes (mode test)')
                ->numeric()
                ->nullable(),
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
                ->label('Programmer l\'import'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')->label('UUID')->copyable()->limit(8),
                Tables\Columns\TextColumn::make('config.name')->label('Config'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Parse')
                    ->badge()
                    ->color(fn (JobStatus $state): string => $state->color())
                    ->formatStateUsing(fn (JobStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('import_status')
                    ->label('Import')
                    ->badge()
                    ->color(fn (ImportStatus $state): string => $state->color())
                    ->formatStateUsing(fn (ImportStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('processed_rows')
                    ->label('Lignes')
                    ->formatStateUsing(fn ($state, ImportJob $record): string => $state.' / '.($record->total_rows ?? '?')),
                Tables\Columns\TextColumn::make('staging_count')->label('Staging')->numeric(),
                Tables\Columns\TextColumn::make('imported_count')->label('Importés')->numeric(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d/m/Y H:i')->label('Créé'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(JobStatus::cases())->mapWithKeys(fn (JobStatus $s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('import_status')
                    ->options(collect(ImportStatus::cases())->mapWithKeys(fn (ImportStatus $s) => [$s->value => $s->label()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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
