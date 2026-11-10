<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Extensions;

use Lunar\Admin\Filament\Resources\BrandResource\Pages\EditBrand;
use Lunar\Admin\Filament\Resources\BrandResource\Pages\ManageBrandUrls;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\StorefrontCms\Filament\Pages\ManageBrandContent;

/**
 * Remplace l'édition Lunar d'une marque par la fiche unifiée ManageBrandContent
 * (informations, URL, logo, SEO + page-builder) sur la route `edit` : lien
 * « Modifier » de la liste, recherche globale et redirection après création
 * y mènent. Onglets restants : Marque, Produits, Collections.
 *
 * La route `urls` reste enregistrée (accès direct à la gestion multi-URLs),
 * mais sort des onglets.
 */
class BrandContentExtension extends ResourceExtension
{
    /**
     * @param  array<string, mixed>  $pages
     * @return array<string, mixed>
     */
    public function extendPages(array $pages): array
    {
        $pages['edit'] = ManageBrandContent::route('/{record}/edit');

        return $pages;
    }

    /**
     * @param  array<int, class-string>  $pages
     * @return array<int, class-string>
     */
    public function extendSubNavigation(array $pages): array
    {
        $pages = array_values(array_filter(
            $pages,
            fn (string $page): bool => ! in_array($page, [EditBrand::class, ManageBrandUrls::class], true),
        ));

        return [ManageBrandContent::class, ...$pages];
    }
}
