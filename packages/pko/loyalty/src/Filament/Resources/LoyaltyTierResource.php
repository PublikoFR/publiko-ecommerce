<?php

declare(strict_types=1);

namespace Pko\Loyalty\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\Loyalty\Enums\GiftType;
use Pko\Loyalty\Filament\Resources\LoyaltyTierResource\Pages;
use Pko\Loyalty\Models\LoyaltyTier;

class LoyaltyTierResource extends BaseResource
{
    protected static ?string $model = LoyaltyTier::class;

    protected static ?int $navigationSort = 50;

    public static function getLabel(): string
    {
        return 'Palier de fidélité';
    }

    public static function getPluralLabel(): string
    {
        return 'Paliers de fidélité';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Marketing';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-trophy';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nom du palier')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('points_required')
                ->label('Points requis')
                ->required()
                ->numeric()
                ->minValue(1),
            Forms\Components\Select::make('gift_type')
                ->label('Type de cadeau')
                ->options(GiftType::options())
                ->default(GiftType::Voucher->value)
                ->required()
                ->live(),
            static::centsInput('voucher_amount', "Montant du bon d'achat (HT)", GiftType::Voucher)
                ->helperText("Bon à usage unique, réservé au client, valable 60 jours. S'il dépasse le montant de la commande, le reste est perdu."),
            static::centsInput('franco_threshold', 'Seuil de franco abaissé (HT)', GiftType::Franco)
                ->helperText('Montant de commande HT à partir duquel le port est offert au client.'),
            static::centsInput('bfa_fixed_amount', 'BFA — montant fixe (HT)', GiftType::Bfa, required: false),
            Forms\Components\TextInput::make('bfa_percent')
                ->label('BFA — pourcentage')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->step(0.01)
                ->suffix('%')
                ->visible(fn (Forms\Get $get): bool => $get('gift_type') === GiftType::Bfa->value)
                ->requiredWithout('bfa_fixed_amount')
                ->validationMessages(['required_without' => 'Renseigner un montant fixe, un pourcentage, ou les deux.']),
            Forms\Components\Toggle::make('active')
                ->label('Actif')
                ->default(true),
        ]);
    }

    /**
     * Montant saisi en euros, stocké en cents. Masqué (et vidé) quand le type
     * de cadeau ne le concerne pas : un palier ne porte que les champs de son type.
     */
    protected static function centsInput(string $name, string $label, GiftType $type, bool $required = true): Forms\Components\TextInput
    {
        $isType = fn (Forms\Get $get): bool => $get('gift_type') === $type->value;

        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue($required ? 0.01 : 0)
            ->step(0.01)
            ->suffix('€')
            ->visible($isType)
            ->required(fn (Forms\Get $get): bool => $required && $isType($get))
            ->formatStateUsing(fn ($state): ?string => $state !== null ? number_format((int) $state / 100, 2, '.', '') : null)
            ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) round((float) $state * 100) : null);
    }

    /**
     * Les champs masqués ne sont pas envoyés : on remet à null ceux d'un autre
     * type pour qu'un palier passé de « bon d'achat » à « BFA » ne garde pas un
     * montant de bon fantôme.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function clearOtherTypeFields(array $data): array
    {
        $type = GiftType::tryFrom((string) ($data['gift_type'] ?? '')) ?? GiftType::Voucher;

        $fields = [
            GiftType::Voucher->value => ['voucher_amount'],
            GiftType::Franco->value => ['franco_threshold'],
            GiftType::Bfa->value => ['bfa_fixed_amount', 'bfa_percent'],
        ];

        foreach ($fields as $owner => $names) {
            if ($owner === $type->value) {
                continue;
            }

            foreach ($names as $name) {
                $data[$name] = null;
            }
        }

        return $data;
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->reorderable('position', false)
            ->defaultSort('points_required')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Palier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('points_required')
                    ->label('Points')
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('gift_type')
                    ->label('Cadeau')
                    ->badge()
                    ->formatStateUsing(fn (?GiftType $state): string => $state?->label() ?? '—'),
                Tables\Columns\TextColumn::make('gift_label')
                    ->label('Détail')
                    ->getStateUsing(fn (LoyaltyTier $record): string => $record->giftLabel()),
                Tables\Columns\IconColumn::make('active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getDefaultPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyTiers::route('/'),
            'create' => Pages\CreateLoyaltyTier::route('/create'),
            'edit' => Pages\EditLoyaltyTier::route('/{record}/edit'),
        ];
    }
}
