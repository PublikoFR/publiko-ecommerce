<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Pko\StorefrontCms\Models\Setting;

class StorefrontSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Storefront';

    public static function getNavigationLabel(): string
    {
        return __('pko-storefront-cms::admin.storefront_settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pko-storefront-cms::admin.storefront_settings.title');
    }

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 50;

    protected static string $view = 'storefront-cms::filament.pages.storefront-settings';

    protected static ?string $slug = 'storefront-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->loadCurrent());
    }

    protected function loadCurrent(): array
    {
        $config = config('storefront');

        return [
            'brand_name' => Setting::get('brand.name', config('app.name')),
            'brand_tagline' => Setting::get('brand.tagline'),
            'brand_meta_description' => Setting::get('brand.meta_description'),
            'contact_phone' => Setting::get('contact.phone', $config['contact']['phone'] ?? null),
            'contact_email' => Setting::get('contact.email', $config['contact']['email'] ?? null),
            'contact_tagline' => Setting::get('contact.tagline', $config['contact']['tagline'] ?? null),
            'banner_enabled' => (bool) Setting::get('banner.enabled', $config['banner']['enabled'] ?? true),
            'banner_text' => Setting::get('banner.text', $config['banner']['text'] ?? null),
            'banner_icon' => Setting::get('banner.icon', $config['banner']['icon'] ?? 'truck'),
            'shipping_free_cents' => (int) Setting::get('shipping.free_threshold_cents', $config['shipping']['free_threshold_cents'] ?? 12500),
            'social_facebook' => Setting::get('social.facebook', $config['social']['facebook'] ?? null),
            'social_instagram' => Setting::get('social.instagram', $config['social']['instagram'] ?? null),
            'social_linkedin' => Setting::get('social.linkedin', $config['social']['linkedin'] ?? null),
            'social_youtube' => Setting::get('social.youtube', $config['social']['youtube'] ?? null),
            'usps' => Setting::get('usps', $config['usps'] ?? []),
            'featured_collection_slug' => Setting::get('home.featured_collection_slug', $config['home']['featured_collection_slug'] ?? null),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identité de la boutique')->schema([
                TextInput::make('brand_name')
                    ->label('Nom de la boutique')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Affiché dans le header, le titre des pages, les e-mails et le back-office.'),
                TextInput::make('brand_tagline')
                    ->label('Accroche')
                    ->maxLength(150)
                    ->placeholder('Ex: Distributeur pro du bâtiment'),
                Textarea::make('brand_meta_description')
                    ->label('Description (SEO)')
                    ->rows(2)
                    ->maxLength(300)
                    ->helperText('Meta description par défaut des pages du storefront.'),
            ]),
            Section::make('Contact')->schema([
                Grid::make(2)->schema([
                    TextInput::make('contact_phone')->label('Téléphone')->tel()->placeholder('02 XX XX XX XX'),
                    TextInput::make('contact_email')->label('E-mail')->email(),
                ]),
                TextInput::make('contact_tagline')->label('Accroche header')->placeholder("Besoin d'un conseil ?"),
            ]),
            Section::make('Bannière info (sous header)')->schema([
                Toggle::make('banner_enabled')->label('Afficher la bannière'),
                Grid::make(2)->schema([
                    TextInput::make('banner_text')->label('Texte')->placeholder('Livraison offerte dès 125 € HT'),
                    Select::make('banner_icon')->label('Icône')->options([
                        'truck' => 'Camion',
                        'check' => 'Check',
                        'info' => 'Info',
                        'map-pin' => 'Localisation',
                        'phone' => 'Téléphone',
                        'credit-card' => 'Carte',
                    ])->default('truck'),
                ]),
                TextInput::make('shipping_free_cents')->label('Seuil livraison offerte (cents HT)')->numeric()->helperText('Ex: 12500 = 125 €'),
            ]),
            Section::make('Réseaux sociaux')->schema([
                Grid::make(2)->schema([
                    TextInput::make('social_facebook')->label('Facebook')->url()->prefix('https://'),
                    TextInput::make('social_instagram')->label('Instagram')->url()->prefix('https://'),
                    TextInput::make('social_linkedin')->label('LinkedIn')->url()->prefix('https://'),
                    TextInput::make('social_youtube')->label('YouTube')->url()->prefix('https://'),
                ]),
            ])->collapsible(),
            Section::make('USPs (bandeau footer)')->schema([
                Repeater::make('usps')
                    ->label('')
                    ->schema([
                        Select::make('icon')->label('Icône')->options([
                            'map-pin' => 'Localisation',
                            'users' => 'Personnes',
                            'truck' => 'Camion',
                            'credit-card' => 'Carte',
                            'check' => 'Check',
                            'phone' => 'Téléphone',
                        ])->required(),
                        TextInput::make('title')->label('Titre')->required()->maxLength(80),
                        TextInput::make('subtitle')->label('Sous-titre')->maxLength(120),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderableWithButtons()
                    ->addActionLabel('Ajouter un USP'),
            ])->collapsed(),
            Section::make('Produits vedettes home')->schema([
                TextInput::make('featured_collection_slug')->label('Slug de la collection affichée en home')->placeholder('ex: nouveautes (vide = derniers produits)'),
            ])->collapsed(),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('brand.name', $data['brand_name']);
        Setting::set('brand.tagline', $data['brand_tagline']);
        Setting::set('brand.meta_description', $data['brand_meta_description']);
        Setting::set('contact.phone', $data['contact_phone']);
        Setting::set('contact.email', $data['contact_email']);
        Setting::set('contact.tagline', $data['contact_tagline']);
        Setting::set('banner.enabled', (bool) $data['banner_enabled']);
        Setting::set('banner.text', $data['banner_text']);
        Setting::set('banner.icon', $data['banner_icon']);
        Setting::set('shipping.free_threshold_cents', (int) $data['shipping_free_cents']);
        Setting::set('social.facebook', $data['social_facebook']);
        Setting::set('social.instagram', $data['social_instagram']);
        Setting::set('social.linkedin', $data['social_linkedin']);
        Setting::set('social.youtube', $data['social_youtube']);
        Setting::set('usps', $data['usps'] ?? []);
        Setting::set('home.featured_collection_slug', $data['featured_collection_slug']);

        Notification::make()->success()->title('Paramètres enregistrés')->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer')
                ->submit('save'),
        ];
    }
}
