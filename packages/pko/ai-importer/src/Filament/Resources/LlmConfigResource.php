<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\AiImporter\Enums\LlmProviderName;
use Pko\AiImporter\Filament\Resources\LlmConfigResource\Pages;
use Pko\AiImporter\Models\LlmConfig;

class LlmConfigResource extends BaseResource
{
    protected static ?string $model = LlmConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 30;

    public static function getLabel(): string
    {
        return 'Configuration LLM';
    }

    public static function getPluralLabel(): string
    {
        return 'Configurations LLM';
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
                ->maxLength(64),
            Forms\Components\Select::make('provider')
                ->label('Provider')
                ->options(collect(LlmProviderName::cases())
                    ->mapWithKeys(fn (LlmProviderName $p) => [$p->value => $p->label()])
                    ->toArray())
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('model')
                ->label('Modèle')
                ->required()
                ->placeholder('claude-sonnet-4-6 / gpt-4o')
                ->maxLength(64),
            Forms\Components\TextInput::make('api_key')
                ->label('Clé API')
                ->password()
                ->revealable()
                ->required()
                ->helperText('Stockée chiffrée via la clé applicative Laravel.'),
            Forms\Components\KeyValue::make('options')
                ->label('Options provider')
                ->keyLabel('Clé')
                ->valueLabel('Valeur')
                ->addActionLabel('Ajouter une option')
                ->nullable(),
            Forms\Components\Toggle::make('is_default')
                ->label('Configuration par défaut'),
            Forms\Components\Toggle::make('active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider')
                    ->badge()
                    ->formatStateUsing(fn (LlmProviderName $state): string => $state->label()),
                Tables\Columns\TextColumn::make('model')->label('Modèle'),
                Tables\Columns\IconColumn::make('is_default')->label('Défaut')->boolean(),
                Tables\Columns\IconColumn::make('active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d/m/Y H:i')->label('Màj'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLlmConfigs::route('/'),
            'create' => Pages\CreateLlmConfig::route('/create'),
            'edit' => Pages\EditLlmConfig::route('/{record}/edit'),
        ];
    }
}
