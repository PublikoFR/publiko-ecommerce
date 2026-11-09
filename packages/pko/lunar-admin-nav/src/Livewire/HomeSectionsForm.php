<?php

declare(strict_types=1);

namespace Pko\AdminNav\Livewire;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\View\View;
use Livewire\Component;
use Lunar\Models\Collection;
use Pko\StorefrontCms\Filament\Pages\Settings\NavigationSettingsSchema;
use Pko\StorefrontCms\Models\Setting;
use Pko\StorefrontCms\Support\HomeSolutionZones;

/**
 * Onglet « Sections » du hub Page d'accueil (FEAT-009) : schéma des solutions
 * (zone → collection), raccourcis services, « La sélection pour les pros » et
 * bandeau pro.
 * Persisté dans pko_storefront_settings (clés home.*), relu par le front via
 * config('storefront.home.*').
 */
class HomeSectionsForm extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $solutions = Setting::get('home.solutions');

        $this->form->fill([
            'solutions' => is_array($solutions) && $solutions !== [] ? $solutions : HomeSolutionZones::defaultEntries(),
            'services' => Setting::get('home.services', config('storefront.home.services', [])),
            'selection' => Setting::get('home.selection', config('storefront.home.selection', [])),
            'community_url' => Setting::get('home.community_url', config('storefront.home.community_url')),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Schéma « À chaque installation, sa solution »')
                ->description('Par défaut, chaque zone pointe automatiquement vers la catégorie de premier niveau qui porte son nom (Portails, Volets…). Choisissez une collection pour imposer un autre lien, ou masquez la zone. Libellé et description vides = textes par défaut de la zone.')
                ->schema([
                    Repeater::make('solutions')
                        ->label('')
                        ->schema([
                            Select::make('zone')
                                ->label('Zone du schéma')
                                ->options(HomeSolutionZones::options())
                                ->required()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            Select::make('collection_id')
                                ->label('Collection')
                                ->options(fn (): array => self::collectionOptions())
                                ->placeholder('Automatique (catégorie du même nom)')
                                ->searchable(),
                            TextInput::make('label')->label('Libellé affiché')->maxLength(60),
                            TextInput::make('description')->label('Description')->maxLength(160),
                            Toggle::make('hidden')->label('Masquer cette zone'),
                        ])
                        ->columns(2)
                        ->maxItems(count(HomeSolutionZones::all()))
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->addActionLabel('Ajouter une zone')
                        ->itemLabel(fn (array $state): ?string => HomeSolutionZones::options()[$state['zone'] ?? ''] ?? null),
                ])->collapsible(),

            Section::make('Raccourcis services (sous le schéma)')
                ->description('Rangée de boutons sous le schéma. Un bouton sans URL est masqué.')
                ->schema([
                    Repeater::make('services')
                        ->label('')
                        ->schema([
                            Select::make('icon')->label('Icône')->options([
                                'refresh-cw' => 'Flèches (remplacement)',
                                'camera' => 'Appareil photo (identification)',
                                'clipboard-pen' => 'Bloc-notes (devis)',
                                'document' => 'Document',
                                'phone' => 'Téléphone',
                                'headset' => 'Casque (assistance)',
                            ])->required(),
                            TextInput::make('title')->label('Titre')->required()->maxLength(60),
                            TextInput::make('subtitle')->label('Sous-titre')->maxLength(100),
                            NavigationSettingsSchema::hrefInput('href', 'URL'),
                        ])
                        ->columns(2)
                        ->maxItems(6)
                        ->defaultItems(0)
                        ->reorderableWithButtons()
                        ->addActionLabel('Ajouter un raccourci')
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                ])->collapsible(),

            Section::make('« La sélection pour les pros »')
                ->description('Raccourcis affichés sous le schéma. Une carte sans URL est masquée.')
                ->schema([
                    Repeater::make('selection')
                        ->label('')
                        ->schema([
                            Select::make('icon')->label('Icône')->options([
                                'tag' => 'Étiquette (offres)',
                                'sparkles' => 'Étincelles (nouveautés)',
                                'package-open' => 'Carton (déstockage)',
                                'percent' => 'Pourcentage',
                                'star' => 'Étoile',
                                'gift' => 'Cadeau',
                            ])->required(),
                            TextInput::make('title')->label('Titre')->required()->maxLength(60),
                            TextInput::make('subtitle')->label('Sous-titre')->maxLength(100),
                            NavigationSettingsSchema::hrefInput('href', 'URL'),
                        ])
                        ->columns(2)
                        ->maxItems(6)
                        ->defaultItems(0)
                        ->reorderableWithButtons()
                        ->addActionLabel('Ajouter un raccourci')
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                ])->collapsible(),

            Section::make('Bandeau « Votre espace professionnel »')
                ->schema([
                    NavigationSettingsSchema::hrefInput('community_url', 'URL de la communauté')
                        ->helperText('Vide = ligne « communauté » masquée.'),
                ])->collapsible(),

            Actions::make([
                Action::make('save')->label('Enregistrer')->submit('save'),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('home.solutions', array_values(array_map(fn (array $row): array => [
            'zone' => $row['zone'] ?? null,
            'collection_id' => isset($row['collection_id']) && $row['collection_id'] !== '' ? (int) $row['collection_id'] : null,
            'label' => ($row['label'] ?? '') !== '' ? $row['label'] : null,
            'description' => ($row['description'] ?? '') !== '' ? $row['description'] : null,
            'hidden' => (bool) ($row['hidden'] ?? false),
        ], $data['solutions'] ?? [])));

        Setting::set('home.services', self::cards($data['services'] ?? [], 'arrow-right'));

        Setting::set('home.selection', self::cards($data['selection'] ?? [], 'tag'));

        // null = ligne masquée : le merge config ignore null (cf.
        // StorefrontCmsServiceProvider) et le défaut de config est lui-même vide.
        Setting::set('home.community_url', ($data['community_url'] ?? '') !== '' ? $data['community_url'] : null);

        Notification::make()->success()->title('Sections de la page d\'accueil enregistrées')->send();
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{icon: string, title: string, subtitle: ?string, href: ?string}>
     */
    private static function cards(array $rows, string $defaultIcon): array
    {
        return array_values(array_map(fn (array $row): array => [
            'icon' => $row['icon'] ?? $defaultIcon,
            'title' => $row['title'] ?? '',
            'subtitle' => ($row['subtitle'] ?? '') !== '' ? $row['subtitle'] : null,
            'href' => ($row['href'] ?? '') !== '' ? $row['href'] : null,
        ], array_filter($rows, 'is_array')));
    }

    /**
     * Collections du catalogue, libellées par leur chemin (« Parent › Enfant »).
     *
     * @return array<int, string>
     */
    public static function collectionOptions(): array
    {
        $all = Collection::query()->orderBy('_lft')->get(['id', 'parent_id', 'attribute_data']);
        $byId = $all->keyBy('id');

        return $all->mapWithKeys(function (Collection $collection) use ($byId): array {
            $path = [];
            $current = $collection;
            $guard = 0;
            while ($current && $guard++ < 10) {
                array_unshift($path, (string) $current->translateAttribute('name'));
                $current = $current->parent_id ? $byId->get($current->parent_id) : null;
            }

            return [$collection->id => implode(' › ', array_filter($path)) ?: '#'.$collection->id];
        })->all();
    }

    public function render(): View
    {
        return view('admin-nav::livewire.home-sections-form');
    }
}
