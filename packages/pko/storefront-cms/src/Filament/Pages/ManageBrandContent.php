<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Models\Brand;
use Pko\StorefrontCms\Models\BrandPage;

/**
 * Sous-page BrandResource : gestion layout + SEO + contenu page-builder.
 * Utilise le même composant Livewire que Post/Page — universel.
 */
class ManageBrandContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = BrandResource::class;

    protected static string $view = 'storefront-cms::filament.pages.manage-brand-content';

    public static function getNavigationLabel(): string
    {
        return __('pko-storefront-cms::admin.brand_content.nav');
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    /** @var Brand */
    public $record;

    /** @var array<string,mixed> */
    public array $data = [];

    public BrandPage $brandPage;

    public static function getSlug(): string
    {
        return 'brands/{record}/content';
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
            'layout' => $this->brandPage->layout,
            'seoTitle' => $this->brandPage->seo_title,
            'seoDescription' => $this->brandPage->seo_description,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('layout')
                    ->label('Layout Blade (optionnel)')
                    ->maxLength(255)
                    ->placeholder('storefront-cms::brands.show')
                    ->helperText('Nom de la vue Blade. Vide = layout par défaut.'),
                TextInput::make('seoTitle')->label('SEO Titre')->maxLength(255),
                TextInput::make('seoDescription')->label('SEO Description')->maxLength(500),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $this->brandPage->update([
            'layout' => $data['layout'] ?? null,
            'seo_title' => $data['seoTitle'] ?? null,
            'seo_description' => $data['seoDescription'] ?? null,
        ]);

        Notification::make()->title('Métadonnées enregistrées')->success()->send();
    }

    public function getTitle(): string
    {
        return 'Contenu — '.$this->record->name;
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
}
