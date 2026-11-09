<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Contracts\Support\Htmlable;
use Pko\AdminNav\Filament\Clusters\PkoShopPaymentCluster;
use Pko\StorefrontCms\Filament\Pages\Settings\NavigationSettingsSchema;
use Pko\StorefrontCms\Models\Setting;
use Pko\StorefrontCms\Support\SparePartsCatalog;

class StorefrontSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $cluster = PkoShopPaymentCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

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

        return NavigationSettingsSchema::load() + [
            'brand_name' => Setting::get('brand.name', config('app.name')),
            'brand_logo' => Setting::get('brand.logo'),
            'brand_logo_dark' => Setting::get('brand.logo_dark'),
            'brand_favicon' => Setting::get('brand.favicon'),
            'brand_tagline' => Setting::get('brand.tagline'),
            'brand_meta_description' => Setting::get('brand.meta_description'),
            'contact_phone' => Setting::get('contact.phone', $config['contact']['phone'] ?? null),
            'contact_email' => Setting::get('contact.email', $config['contact']['email'] ?? null),
            'admin_email' => Setting::get('admin_email', ''),
            'contact_tagline' => Setting::get('contact.tagline', $config['contact']['tagline'] ?? null),
            'social_facebook' => Setting::get('social.facebook', $config['social']['facebook'] ?? null),
            'social_instagram' => Setting::get('social.instagram', $config['social']['instagram'] ?? null),
            'social_linkedin' => Setting::get('social.linkedin', $config['social']['linkedin'] ?? null),
            'social_youtube' => Setting::get('social.youtube', $config['social']['youtube'] ?? null),
            'spare_parts_collection_id' => Setting::get(SparePartsCatalog::SETTING_KEY),
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
                Grid::make(2)->schema([
                    FileUpload::make('brand_logo')
                        ->label('Logo — thème clair')
                        ->image()
                        ->disk('public')
                        ->directory('brand')
                        ->visibility('public')
                        ->imageEditor()
                        ->maxSize(2048)
                        ->helperText('Header du site + back-office en thème clair. PNG/SVG à fond transparent. Vide = logo textuel de repli.'),
                    FileUpload::make('brand_logo_dark')
                        ->label('Logo — thème sombre')
                        ->image()
                        ->disk('public')
                        ->directory('brand')
                        ->visibility('public')
                        ->imageEditor()
                        ->maxSize(2048)
                        ->helperText('Back-office en thème sombre. Utilisez une version claire du logo (texte/traits clairs). Vide = logo textuel de repli.'),
                ]),
                FileUpload::make('brand_favicon')
                    ->label('Favicon')
                    ->image()
                    ->disk('public')
                    ->directory('brand')
                    ->visibility('public')
                    ->maxSize(1024)
                    ->helperText('Icône de l\'onglet navigateur, commune aux deux thèmes. PNG/ICO carré (32×32 ou 64×64) recommandé.'),
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
                TextInput::make('admin_email')
                    ->label('E-mail notifications équipe')
                    ->email()
                    ->helperText('Destinataire des e-mails internes : nouvelle inscription, nouvelle commande, virement à rapprocher, palier de fidélité.'),
                TextInput::make('contact_tagline')->label('Accroche header')->placeholder("Besoin d'un conseil ?"),
            ]),
            ...NavigationSettingsSchema::sections(),
            Section::make('Catalogue')->schema([
                Select::make('spare_parts_collection_id')
                    ->label('Catégorie racine « Pièces détachées »')
                    ->options(fn (): array => SparePartsCatalog::collectionOptions())
                    ->searchable()
                    ->placeholder('Par défaut : catégorie d\'URL « '.SparePartsCatalog::DEFAULT_SLUG.' »')
                    ->helperText('Cette catégorie et toutes ses sous-catégories, ainsi que les fiches des produits qui y sont rattachés, affichent le bandeau « Un doute sur la pièce à commander ? » (envoi de photo pour identification).'),
            ])->collapsible(),
            Section::make('Réseaux sociaux')->schema([
                Grid::make(2)->schema([
                    TextInput::make('social_facebook')->label('Facebook')->url()->prefix('https://'),
                    TextInput::make('social_instagram')->label('Instagram')->url()->prefix('https://'),
                    TextInput::make('social_linkedin')->label('LinkedIn')->url()->prefix('https://'),
                    TextInput::make('social_youtube')->label('YouTube')->url()->prefix('https://'),
                ]),
            ])->collapsible(),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('brand.name', $data['brand_name']);
        Setting::set('brand.logo', $data['brand_logo'] ?? null);
        Setting::set('brand.logo_dark', $data['brand_logo_dark'] ?? null);
        Setting::set('brand.favicon', $data['brand_favicon'] ?? null);
        Setting::set('brand.tagline', $data['brand_tagline']);
        Setting::set('brand.meta_description', $data['brand_meta_description']);
        Setting::set('contact.phone', $data['contact_phone']);
        Setting::set('contact.email', $data['contact_email']);
        Setting::set('admin_email', $data['admin_email'] ?? '');
        Setting::set('contact.tagline', $data['contact_tagline']);
        Setting::set('social.facebook', $data['social_facebook']);
        Setting::set('social.instagram', $data['social_instagram']);
        Setting::set('social.linkedin', $data['social_linkedin']);
        Setting::set('social.youtube', $data['social_youtube']);
        Setting::set(SparePartsCatalog::SETTING_KEY, filled($data['spare_parts_collection_id'] ?? null) ? (int) $data['spare_parts_collection_id'] : null);
        NavigationSettingsSchema::save($data);

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
