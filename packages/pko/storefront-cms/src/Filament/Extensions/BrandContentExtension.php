<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Extensions;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\StorefrontCms\Filament\Pages\ManageBrandContent;

/**
 * Injecte la page "Contenu" (page-builder universel) dans BrandResource.
 * Ajoute aussi un raccourci visible dans le formulaire edit.
 */
class BrandContentExtension extends ResourceExtension
{
    /**
     * @param  array<string, mixed>  $pages
     * @return array<string, mixed>
     */
    public function extendPages(array $pages): array
    {
        $pages['content'] = ManageBrandContent::route('/{record}/content');

        return $pages;
    }

    /**
     * @param  array<int, class-string>  $pages
     * @return array<int, class-string>
     */
    public function extendSubNavigation(array $pages): array
    {
        $pages[] = ManageBrandContent::class;

        return $pages;
    }

    /**
     * Ajoute une section "Page marque" visible dans le formulaire d'édition Brand
     * avec un bouton direct vers la page builder. Plus discoverable que la sub-nav
     * en position End.
     */
    public function extendForm(Form $form): Form
    {
        return $form->schema([
            ...$form->getComponents(),
            Section::make('Page marque (builder)')
                ->description('Éditez le layout et le contenu visuel de la page publique /marque/{slug}.')
                ->schema([
                    Actions::make([
                        Action::make('editBrandContent')
                            ->label('Ouvrir l\'éditeur de contenu')
                            ->icon('heroicon-o-document-text')
                            ->url(fn ($record) => $record ? ManageBrandContent::getUrl(['record' => $record]) : null)
                            ->visible(fn ($record) => $record !== null),
                    ]),
                ])
                ->collapsible(),
        ]);
    }
}
