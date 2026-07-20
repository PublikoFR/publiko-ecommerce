<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Filament\Extensions;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\CustomerAuth\Filament\Resources\PkoCustomerResource;

class CustomerProfileExtension extends ResourceExtension
{
    public function extendForm(Form $form): Form
    {
        $titleSelect = Select::make('title')
            ->label('Civilité')
            ->options(['Mr' => 'M.', 'Mme' => 'Mme'])
            ->nullable()
            ->placeholder('—');

        $components = $this->swapTitleComponent($form->getComponents(), $titleSelect);

        return $form->schema([
            ...$components,
            Section::make('Informations B2B')
                ->schema([
                    // SIRET éditable : le modifier relance la vérification INSEE
                    // à l'enregistrement (cf. CustomerSiretExtension::beforeUpdate).
                    // Édition seule (sur create, la vérif passe par l'inscription front).
                    Grid::make(2)->schema([
                        TextInput::make('siret')
                            ->label('SIRET')
                            ->helperText('Le modifier relance la vérification INSEE à l\'enregistrement.')
                            ->visibleOn('edit')
                            ->maxLength(20),
                        Placeholder::make('_naf')
                            ->label('Code NAF / Secteur')
                            ->content(function ($record): string {
                                if (! $record) {
                                    return '—';
                                }
                                $parts = array_filter([
                                    $record->naf_code,
                                    $record->meta['activity'] ?? null,
                                ]);

                                return $parts ? implode(' — ', $parts) : '—';
                            }),
                    ]),

                    // Adresse postale
                    TextInput::make('pko_street')
                        ->label('Rue')
                        ->nullable()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Grid::make(3)->schema([
                        TextInput::make('pko_postcode')
                            ->label('Code postal')
                            ->nullable()
                            ->maxLength(10),
                        TextInput::make('pko_city')
                            ->label('Ville')
                            ->nullable()
                            ->maxLength(100),
                        Select::make('pko_country')
                            ->label('Pays')
                            ->options([
                                'FR' => 'France',
                                'BE' => 'Belgique',
                                'CH' => 'Suisse',
                                'LU' => 'Luxembourg',
                            ])
                            ->default('FR')
                            ->nullable(),
                    ]),

                    // Statut admin + SEPA
                    Grid::make(2)->schema([
                        Select::make('pko_status')
                            ->label('Statut compte')
                            ->options([
                                'pending' => 'En attente',
                                'active' => 'Actif',
                                'banned' => 'Suspendu',
                            ])
                            ->default('pending')
                            ->required(),
                        Toggle::make('sepa_enabled')
                            ->label('Prélèvement SEPA autorisé')
                            ->default(false)
                            ->inline(false),
                    ]),

                    // Indicateur email vérifié (lecture seule, issu du User lié)
                    Placeholder::make('_email_verified')
                        ->label('Email vérifié le')
                        ->content(fn ($record) => $record?->users()->first()?->email_verified_at?->format('d/m/Y à H:i') ?? 'Non vérifié'),
                ])
                ->columns(2)
                ->collapsible(),
        ]);
    }

    public function extendTable(Table $table): Table
    {
        // Un clic sur une ligne ouvre directement le formulaire d'édition
        // (évite le détour par la fiche read-only ; l'œil « voir » reste dispo).
        return $table->recordUrl(
            fn (Model $record): string => PkoCustomerResource::getUrl('edit', ['record' => $record]),
        )->columns([
            ...$table->getColumns(),
            TextColumn::make('pko_status')
                ->label('Statut')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'pending' => 'warning',
                    'banned' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'active' => 'Actif',
                    'pending' => 'En attente',
                    'banned' => 'Suspendu',
                    default => $state,
                }),
        ]);
    }

    /**
     * Remplace récursivement le TextInput "title" de Lunar par un Select Mr/Mme.
     *
     * @param  array<Component>  $components
     * @return array<Component>
     */
    private function swapTitleComponent(array $components, Component $replacement): array
    {
        foreach ($components as $index => $component) {
            if ($component instanceof Field && $component->getName() === 'title') {
                $components[$index] = $replacement;

                return $components;
            }

            // On ne descend que dans les composants dont le schéma est déjà un
            // tableau statique. Certains composants Lunar (ex. Attributes::make())
            // définissent leur schéma via une Closure qui a besoin du container
            // Livewire ($get, $livewire, $record) ; l'évaluer ici, pendant
            // extendForm et hors container, casse avec
            // "Component::$container must not be accessed before initialization".
            if (method_exists($component, 'getChildComponents') && $this->hasArraySchema($component)) {
                $children = $this->swapTitleComponent($component->getChildComponents(), $replacement);
                $component->schema($children);
            }
        }

        return $components;
    }

    /**
     * Vrai si le schéma du composant est un tableau statique (et non une Closure
     * résolue tardivement dans le container). Sûr à évaluer/recurser hors container.
     */
    private function hasArraySchema(Component $component): bool
    {
        if (! property_exists($component, 'childComponents')) {
            return false;
        }

        $property = new \ReflectionProperty($component, 'childComponents');
        $property->setAccessible(true);

        return is_array($property->getValue($component));
    }
}
