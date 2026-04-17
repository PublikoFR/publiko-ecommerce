<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Mde\AiImporter\Filament\Resources\ImporterConfigResource\Pages;
use Mde\AiImporter\Models\ImporterConfig;

class ImporterConfigResource extends BaseResource
{
    protected static ?string $model = ImporterConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    public static function getLabel(): string
    {
        return 'Configuration import';
    }

    public static function getPluralLabel(): string
    {
        return 'Configurations import';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('ai-importer.navigation.group', 'Imports');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nom')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(128),
            Forms\Components\TextInput::make('supplier_name')
                ->label('Fournisseur')
                ->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('config_data')
                ->label('Mapping JSON')
                ->rows(24)
                ->required()
                ->columnSpanFull()
                ->helperText('Phase 1 : édition JSON brut. Un éditeur visuel (actions drag-n-drop) sera ajouté en phase 5.')
                ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}')
                ->dehydrateStateUsing(fn ($state): array => is_string($state) ? (json_decode($state, true) ?? []) : (array) $state)
                ->rules(['json']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('supplier_name')->label('Fournisseur')->searchable(),
                Tables\Columns\TextColumn::make('jobs_count')->counts('jobs')->label('Jobs'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d/m/Y H:i')->label('Màj'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()->excludeAttributes(['name'])->label('Dupliquer'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImporterConfigs::route('/'),
            'create' => Pages\CreateImporterConfig::route('/create'),
            'edit' => Pages\EditImporterConfig::route('/{record}/edit'),
        ];
    }
}
