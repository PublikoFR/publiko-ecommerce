<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Lunar\Admin\Filament\Resources\BrandResource\Pages\ManageBrandMedia;
use Lunar\Admin\Filament\Resources\CollectionResource\Pages\ManageCollectionMedia;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductMedia;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Admin\Support\RelationManagers\MediaRelationManager;
use Pko\StorefrontCms\Filament\Forms\Components\MediaPicker;

/**
 * Hide Lunar's native Spatie MediaLibrary UI on Product/Collection/Brand resources.
 *
 * The unified `pko_mediables` system (HasMediaAttachments trait + MediaPicker field)
 * replaces the Lunar-native media handling. We do not touch vendor/, we only filter
 * pages/sub-navigation/relations/table-columns via Lunar's extension hooks.
 */
class HideLunarMediaExtension extends ResourceExtension
{
    /** @var array<int, class-string> */
    private const MEDIA_PAGE_CLASSES = [
        ManageProductMedia::class,
        ManageCollectionMedia::class,
        ManageBrandMedia::class,
    ];

    /**
     * Remove the 'media' entry from the pages map (route is not registered).
     *
     * @param  array<string, mixed>  $pages
     * @return array<string, mixed>
     */
    public function extendPages(array $pages): array
    {
        unset($pages['media']);

        return $pages;
    }

    /**
     * Filter out Manage{X}Media from the resource's record sub-navigation.
     *
     * @param  array<int, class-string>  $pages
     * @return array<int, class-string>
     */
    public function extendSubNavigation(array $pages): array
    {
        return array_values(array_filter(
            $pages,
            fn ($page) => ! in_array($page, self::MEDIA_PAGE_CLASSES, true),
        ));
    }

    /**
     * Remove the native MediaRelationManager from the relation list.
     *
     * @param  array<int, mixed>  $managers
     * @return array<int, mixed>
     */
    public function getRelations(array $managers): array
    {
        return array_values(array_filter(
            $managers,
            fn ($m) => $m !== MediaRelationManager::class,
        ));
    }

    /**
     * Inject the unified MediaPicker (thumbnail single + gallery multi) at the end
     * of the record edit form. Data persists to `pko_mediables` pivot directly —
     * no trait required on the underlying Lunar model.
     */
    public function extendForm(Form $form): Form
    {
        return $form->schema([
            ...$form->getComponents(),
            Section::make('Médias')
                ->description('Image principale et galerie.')
                ->schema([
                    MediaPicker::make('thumbnail')
                        ->label('Image principale')
                        ->mediagroup('thumbnail'),
                    MediaPicker::make('gallery')
                        ->label('Galerie')
                        ->multiple()
                        ->mediagroup('gallery'),
                ])
                ->collapsible(),
        ]);
    }

    /**
     * Strip any SpatieMediaLibraryImageColumn from the resource table
     * (Brand list uses one as the first column).
     */
    public function extendTable(Table $table): Table
    {
        $columns = array_filter(
            $table->getColumns(),
            fn ($column) => ! $column instanceof SpatieMediaLibraryImageColumn,
        );

        return $table->columns(array_values($columns));
    }
}
