<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Filament\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Pko\MailTemplates\Filament\Resources\MailTemplateResource\Pages;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\MailPreview;
use Pko\MailTemplates\Support\MailTemplateRegistry;
use Throwable;

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

                    // Affiché uniquement pour le modèle « Panier non finalisé ».
                    TextInput::make('settings.delay_days')
                        ->label(__('pko-mail-templates::admin.field.delay_days'))
                        ->helperText(__('pko-mail-templates::admin.field.delay_days_help'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(90)
                        ->default(5)
                        ->hidden(fn (Get $get): bool => $get('key') !== 'cart.abandoned'),
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

                TextColumn::make('audience')
                    ->label(__('pko-mail-templates::admin.field.audience'))
                    ->badge()
                    ->state(fn (MailTemplate $record): string => MailTemplateRegistry::audience($record->key))
                    ->formatStateUsing(fn (string $state): string => __('pko-mail-templates::admin.audience.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        MailTemplateRegistry::AUDIENCE_ADMIN => 'warning',
                        MailTemplateRegistry::AUDIENCE_BOTH => 'info',
                        default => 'success',
                    }),

                IconColumn::make('enabled')
                    ->label(__('pko-mail-templates::admin.field.enabled'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('pko-mail-templates::admin.field.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('audience')
                    ->label(__('pko-mail-templates::admin.field.audience'))
                    ->options([
                        MailTemplateRegistry::AUDIENCE_CUSTOMER => __('pko-mail-templates::admin.audience.customer'),
                        MailTemplateRegistry::AUDIENCE_ADMIN => __('pko-mail-templates::admin.audience.admin'),
                        MailTemplateRegistry::AUDIENCE_BOTH => __('pko-mail-templates::admin.audience.both'),
                    ])
                    // `audience` vient du registre (code), pas d'une colonne :
                    // le filtre se fait donc sur la liste des clés correspondantes.
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        $keys = array_keys(array_filter(
                            MailTemplateRegistry::all(),
                            static fn (array $meta): bool => $meta['audience'] === $value,
                        ));

                        return $query->whereIn('key', $keys);
                    }),
            ])
            ->actions([
                Action::make('preview')
                    ->label(__('pko-mail-templates::admin.action.preview'))
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->tooltip(__('pko-mail-templates::admin.action.preview_tooltip'))
                    ->slideOver()
                    ->modalHeading(fn (MailTemplate $record): string => MailTemplateRegistry::has($record->key)
                        ? MailTemplateRegistry::get($record->key)['label']
                        : $record->key)
                    ->modalContent(fn (MailTemplate $record) => view(
                        'pko-mail-templates::filament.preview-panel',
                        ['html' => MailPreview::render($record->key)],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('pko-mail-templates::admin.action.close')),

                Action::make('send_test')
                    ->label(__('pko-mail-templates::admin.action.send_test'))
                    ->icon('heroicon-o-paper-airplane')
                    ->iconButton()
                    ->color('gray')
                    ->tooltip(__('pko-mail-templates::admin.action.send_test_tooltip'))
                    // Un modèle désactivé ou sans contenu n'a rien à envoyer.
                    ->visible(fn (MailTemplate $record): bool => MailPreview::mail($record->key)->shouldSend())
                    ->form(fn (): array => [
                        TextInput::make('recipient')
                            ->label(__('pko-mail-templates::admin.field.test_recipient'))
                            ->helperText(__('pko-mail-templates::admin.field.test_recipient_help'))
                            ->email()
                            ->required()
                            ->default(MailPreview::defaultTestRecipient()),
                    ])
                    ->action(function (MailTemplate $record, array $data): void {
                        $recipient = (string) $data['recipient'];

                        try {
                            MailPreview::sendTest($record->key, $recipient);
                        } catch (Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('pko-mail-templates::admin.test.failed'))
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('pko-mail-templates::admin.test.sent', ['email' => $recipient]))
                            ->send();
                    }),

                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('pko-mail-templates::admin.action.edit_tooltip')),
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
