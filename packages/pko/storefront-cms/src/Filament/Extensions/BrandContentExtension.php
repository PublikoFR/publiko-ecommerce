<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Extensions;

use Lunar\Admin\Support\Extending\ResourceExtension;
use Pko\StorefrontCms\Filament\Pages\ManageBrandContent;

/**
 * Injecte la page "Contenu" (page-builder universel) dans BrandResource.
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
}
