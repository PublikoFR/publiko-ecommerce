<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Filament\Extensions;

use Closure;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\CatalogFeatures\Support\BrandReferencePrefix;

/**
 * Champ « Préfixe de référence » de la marque (BrandResource Lunar).
 *
 * Création : laissé vide, la proposition par défaut (3 lettres du nom) est
 * posée si elle est libre. Collision : refus avec suggestions, l'admin choisit.
 * Le préfixe ne réécrit aucun SKU existant : cf. `pko:references:sync`.
 */
class BrandReferencePrefixExtension extends ResourceExtension
{
    public function extendForm(Form $form): Form
    {
        return $form->schema([
            ...$form->getComponents(),
            Section::make('Référence produit')
                ->description('Référence boutique d\'un produit = préfixe de la marque + référence fabricant (ex. SOM + 1240385 → SOM1240385).')
                ->schema([
                    self::prefixField(),
                ])
                ->collapsible(),
        ]);
    }

    public static function prefixField(): TextInput
    {
        return TextInput::make('pko_ref_prefix')
            ->label('Préfixe de référence')
            ->helperText('2 à 5 lettres ou chiffres en majuscules. Vide à la création : 3 premières lettres du nom, si elles sont libres.')
            // Format (2-5 caractères, après trim + majuscules) validé par la règle
            // ci-dessous : pas de maxLength() serveur, qui compterait les espaces.
            ->extraInputAttributes(['style' => 'text-transform: uppercase', 'maxlength' => 7])
            ->placeholder(fn (Get $get): ?string => BrandReferencePrefix::suggest($get('name')))
            ->suffixAction(
                Action::make('suggestRefPrefix')
                    ->icon('heroicon-o-sparkles')
                    ->tooltip('Proposer un préfixe libre')
                    ->action(function (Get $get, Set $set, ?Model $record): void {
                        $ignore = $record?->getKey();
                        $prefix = BrandReferencePrefix::freeSuggestion($get('name'), $ignore)
                            ?? (BrandReferencePrefix::alternatives($get('name'), $ignore, 1)[0] ?? null);
                        $set('pko_ref_prefix', $prefix);
                    }),
            )
            ->rule(static fn (Get $get, ?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                $prefix = BrandReferencePrefix::normalize(is_scalar($value) ? (string) $value : null);
                if ($prefix === null) {
                    return;
                }
                if (! BrandReferencePrefix::isValid($prefix)) {
                    $fail('Le préfixe doit compter 2 à 5 lettres ou chiffres (A-Z, 0-9).');

                    return;
                }
                $ignore = $record?->getKey();
                $owner = BrandReferencePrefix::owner($prefix, $ignore);
                if ($owner !== null) {
                    $fail(BrandReferencePrefix::collisionMessage($prefix, $owner, $get('name'), $ignore));
                }
            })
            ->dehydrateStateUsing(static function (?string $state, Get $get, ?Model $record): ?string {
                $prefix = BrandReferencePrefix::normalize($state);

                // Création sans saisie : proposition par défaut si libre.
                if ($prefix === null && $record === null) {
                    return BrandReferencePrefix::freeSuggestion($get('name'));
                }

                return $prefix;
            });
    }

    public function extendTable(Table $table): Table
    {
        return $table->pushColumns([
            TextColumn::make('pko_ref_prefix')
                ->label('Préfixe réf.')
                ->placeholder('—')
                ->searchable()
                ->sortable(),
        ]);
    }
}
