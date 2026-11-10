<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Navigation\NavigationGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Lunar\Admin\Events\ModelUrlsUpdated;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Models\Brand;
use Lunar\Models\Language;
use Lunar\Models\Url;
use Pko\CatalogFeatures\Filament\Extensions\BrandReferencePrefixExtension;
use Pko\LunarMediaCore\Filament\Forms\Components\MediaPicker;
use Pko\StorefrontCms\Models\BrandPage;

/**
 * Fiche marque unifiée (route `edit` de BrandResource) : remplace les onglets
 * Lunar « Informations de base » et « URLs ».
 *
 * Accordéons repliés (informations + SEO, logo, référence produit) puis le
 * page-builder pleine largeur. Le bouton « Enregistrer » de la barre du
 * builder enregistre aussi ce formulaire (événement `page-builder-saved`).
 *
 * Les extensions de formulaire de BrandResource ne s'appliquent PAS ici (form
 * propre à la page) : les champs utiles sont redéclarés explicitement.
 */
class ManageBrandContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = BrandResource::class;

    protected static string $view = 'storefront-cms::filament.pages.manage-brand-content';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    /** @var Brand */
    public $record;

    /** @var array<string,mixed> */
    public array $data = [];

    public BrandPage $brandPage;

    public static function getNavigationLabel(): string
    {
        return __('pko-storefront-cms::admin.brand_content.nav');
    }

    public function mount(int|string $record): void
    {
        /** @var Brand $brand */
        $brand = Brand::query()->findOrFail($record);
        $this->record = $brand;

        $this->brandPage = BrandPage::firstOrNewForBrand((int) $brand->id);
        if (! $this->brandPage->exists) {
            $this->brandPage->content = null;
            $this->brandPage->save();
            $this->brandPage->refresh();
        }

        $this->form->fill([
            'name' => $brand->name,
            'slug' => $this->defaultUrl()?->slug,
            'pko_ref_prefix' => $brand->pko_ref_prefix,
            'seoTitle' => $this->brandPage->seo_title,
            'seoDescription' => $this->brandPage->seo_description,
            'layout' => $this->brandPage->layout,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record)
            ->statePath('data')
            ->schema([
                Section::make(__('pko-storefront-cms::admin.brand_content.info_section'))
                    ->icon('heroicon-o-magnifying-glass')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nom')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('slug')
                                ->label('URL de la page marque')
                                ->prefix('/marque/')
                                ->required()
                                ->maxLength(255)
                                ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state))
                                ->rule(fn (): Closure => $this->uniqueSlugRule()),
                            TextInput::make('seoTitle')->label('SEO Titre')->maxLength(255),
                            TextInput::make('layout')
                                ->label('Layout Blade (optionnel)')
                                ->maxLength(255)
                                ->placeholder('storefront-cms::brands.show')
                                ->helperText('Nom de la vue Blade. Vide = layout par défaut.'),
                        ]),
                        Textarea::make('seoDescription')
                            ->label('SEO Description')
                            ->rows(2)
                            ->maxLength(500),
                    ]),
                Section::make(__('pko-storefront-cms::admin.brand_content.logo_section'))
                    ->description('Affiché sur la liste des marques, les fiches produit et la recherche.')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        MediaPicker::make('logo')
                            ->hiddenLabel()
                            ->mediagroup('logo')
                            ->folder('brands'),
                    ]),
                ...$this->referencePrefixSection(),
            ]);
    }

    /**
     * Événement émis par le page-builder quand on clique « Enregistrer » dans
     * sa barre : une seule action enregistre blocs + informations.
     */
    #[On('page-builder-saved')]
    public function saveFromBuilder(string $modelClass, int $recordId): void
    {
        if ($modelClass === BrandPage::class && $recordId === (int) $this->brandPage->getKey()) {
            $this->save(notify: false);
        }
    }

    public function save(bool $notify = true): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data): void {
            $this->record->update(array_intersect_key($data, array_flip(['name', 'pko_ref_prefix'])));

            $this->brandPage->update([
                'layout' => $data['layout'] ?? null,
                'seo_title' => $data['seoTitle'] ?? null,
                'seo_description' => $data['seoDescription'] ?? null,
            ]);

            $this->persistSlug((string) $data['slug']);

            // MediaPicker (logo) : dehydrated(false), persiste via saveRelationships.
            $this->form->saveRelationships();
        });

        $this->form->fill([...$this->data, 'slug' => $this->defaultUrl()?->slug]);

        if ($notify) {
            Notification::make()->title(__('pko-storefront-cms::admin.brand_content.saved'))->success()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewOnStorefront')
                ->label('Voir la page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => ($slug = $this->defaultUrl()?->slug) ? url('/marque/'.$slug) : null, shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->defaultUrl() !== null),
            // Même garde que EditBrand (Lunar) : refus si la marque a des produits.
            DeleteAction::make()
                ->record($this->record)
                ->successRedirectUrl(BrandResource::getUrl('index'))
                ->before(function (DeleteAction $action): void {
                    if ($this->record->products()->exists()) {
                        Notification::make()
                            ->warning()
                            ->body(__('lunarpanel::brand.action.delete.notification.error_protected'))
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getBreadcrumb(): string
    {
        return __('pko-storefront-cms::admin.brand_content.nav');
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * Expose la sous-navigation BrandResource sur cette page. Sans ce override,
     * ManageBrandContent (qui n'utilise pas InteractsWithRecord) retourne [] et
     * les onglets disparaissent.
     *
     * @return array<NavigationGroup>
     */
    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    /**
     * @return array<string,mixed>
     */
    public function getSubNavigationParameters(): array
    {
        return ['record' => $this->record];
    }

    /**
     * Section « Référence produit » si pko/catalog-features est installé
     * (dépendance souple : storefront-cms ne l'exige pas).
     *
     * @return array<int, Section>
     */
    private function referencePrefixSection(): array
    {
        if (! class_exists(BrandReferencePrefixExtension::class)) {
            return [];
        }

        return [
            Section::make('Référence produit')
                ->description('Référence boutique d\'un produit = préfixe de la marque + référence fabricant (ex. SOM + 1240385 → SOM1240385).')
                ->icon('heroicon-o-hashtag')
                ->collapsible()
                ->collapsed()
                ->schema([
                    BrandReferencePrefixExtension::prefixField(),
                ]),
        ];
    }

    private function defaultUrl(): ?Url
    {
        /** @var Url|null $url */
        $url = $this->record->urls()->where('default', true)->orderBy('language_id')->first()
            ?? $this->record->urls()->orderBy('id')->first();

        return $url;
    }

    private function urlLanguageId(): int
    {
        return (int) ($this->defaultUrl()?->language_id ?? Language::getDefault()?->id);
    }

    /**
     * Unicité du slug par (type d'élément, langue), comme ManageUrlsRelatedRecords
     * de Lunar, en ignorant l'URL par défaut de la marque elle-même.
     */
    private function uniqueSlugRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $slug = Str::slug(is_scalar($value) ? (string) $value : '');
            if ($slug === '') {
                $fail('L\'URL doit contenir au moins une lettre ou un chiffre.');

                return;
            }

            $taken = Url::query()
                ->where('element_type', $this->record->getMorphClass())
                ->where('language_id', $this->urlLanguageId())
                ->where('slug', $slug)
                ->where('element_id', '!=', $this->record->getKey())
                ->exists();

            if ($taken) {
                $fail('Cette URL est déjà utilisée par une autre marque.');
            }
        };
    }

    /**
     * Met à jour (ou crée) l'URL par défaut. Les autres URLs (autres langues,
     * anciennes URLs) ne sont pas touchées.
     */
    private function persistSlug(string $slug): void
    {
        $url = $this->defaultUrl();

        if ($url !== null && $url->slug === $slug && $url->default) {
            return;
        }

        if ($url !== null) {
            $url->update(['slug' => $slug, 'default' => true]);
        } else {
            $this->record->urls()->create([
                'slug' => $slug,
                'language_id' => $this->urlLanguageId(),
                'default' => true,
            ]);
        }

        ModelUrlsUpdated::dispatch($this->record);
    }
}
