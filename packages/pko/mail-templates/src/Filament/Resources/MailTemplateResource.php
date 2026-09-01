<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Filament\Resources;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Pko\MailTemplates\Filament\Resources\MailTemplateResource\Pages;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\MailTemplateRegistry;

class MailTemplateResource extends Resource
{
    protected static ?string $model = MailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('pko-mail-templates::admin.nav');
    }

    public static function getModelLabel(): string
    {
        return __('pko-mail-templates::admin.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pko-mail-templates::admin.model_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('pko-mail-templates::admin.group');
    }

    /** Les modèles sont créés par le seeder : on n'en ajoute pas à la main. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('pko-mail-templates::admin.section.settings'))
                ->schema([
                    TextInput::make('key')
                        ->label(__('pko-mail-templates::admin.field.key'))
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText(fn (Get $get): string => self::placeholderHint((string) $get('key'))),

                    TextInput::make('subject')
                        ->label(__('pko-mail-templates::admin.field.subject'))
                        ->required()
                        ->maxLength(255),

                    Toggle::make('enabled')
                        ->label(__('pko-mail-templates::admin.field.enabled'))
                        ->helperText(__('pko-mail-templates::admin.field.enabled_help')),
                ]),

            Section::make(__('pko-mail-templates::admin.section.content'))
                ->schema([
                    Repeater::make('blocks')
                        ->label(__('pko-mail-templates::admin.field.blocks'))
                        ->schema([
                            Select::make('type')
                                ->label(__('pko-mail-templates::admin.block.type'))
                                ->options([
                                    'paragraph' => __('pko-mail-templates::admin.block.paragraph'),
                                    'heading' => __('pko-mail-templates::admin.block.heading'),
                                    'button' => __('pko-mail-templates::admin.block.button'),
                                    'divider' => __('pko-mail-templates::admin.block.divider'),
                                    'signature' => __('pko-mail-templates::admin.block.signature'),
                                ])
                                ->default('paragraph')
                                ->live()
                                ->required(),

                            Textarea::make('text')
                                ->label(__('pko-mail-templates::admin.block.text'))
                                ->rows(3)
                                ->visible(fn (Get $get): bool => in_array(
                                    $get('type'),
                                    ['paragraph', 'heading', 'signature'],
                                    true,
                                )),

                            TextInput::make('label')
                                ->label(__('pko-mail-templates::admin.block.label'))
                                ->visible(fn (Get $get): bool => $get('type') === 'button'),

                            TextInput::make('url')
                                ->label(__('pko-mail-templates::admin.block.url'))
                                ->visible(fn (Get $get): bool => $get('type') === 'button'),

                            Select::make('variant')
                                ->label(__('pko-mail-templates::admin.block.variant'))
                                ->options([
                                    'primary' => __('pko-mail-templates::admin.block.variant_primary'),
                                    'accent' => __('pko-mail-templates::admin.block.variant_accent'),
                                ])
                                ->default('primary')
                                ->visible(fn (Get $get): bool => $get('type') === 'button'),
                        ])
                        ->itemLabel(fn (array $state): ?string => Str::limit(
                            (string) ($state['text'] ?? $state['label'] ?? $state['type'] ?? ''),
                            60,
                        ))
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(1),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('pko-mail-templates::admin.field.name'))
                    ->formatStateUsing(fn (string $state): string => MailTemplateRegistry::has($state)
                        ? MailTemplateRegistry::get($state)['label']
                        : $state)
                    ->description(fn (MailTemplate $record): string => $record->key)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label(__('pko-mail-templates::admin.field.subject'))
                    ->limit(50)
                    ->searchable(),

                IconColumn::make('enabled')
                    ->label(__('pko-mail-templates::admin.field.enabled'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('pko-mail-templates::admin.field.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('key')
            ->paginated([25, 50]);
    }

    /** Rappel des variables utilisables, affiché sous la clé du modèle. */
    private static function placeholderHint(string $key): string
    {
        if (! MailTemplateRegistry::has($key)) {
            return '';
        }

        $meta = MailTemplateRegistry::get($key);
        $available = implode(', ', array_map(static fn (string $p): string => ':'.$p, $meta['placeholders']));
        $required = implode(', ', array_map(static fn (string $p): string => ':'.$p, $meta['required']));

        $hint = __('pko-mail-templates::admin.hint.available', ['list' => $available]);

        if ($required !== '') {
            $hint .= ' — '.__('pko-mail-templates::admin.hint.required', ['list' => $required]);
        }

        return $hint;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailTemplates::route('/'),
            'edit' => Pages\EditMailTemplate::route('/{record}/edit'),
        ];
    }
}
