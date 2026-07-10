<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\PostResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Pko\StorefrontCms\Filament\Resources\PostResource;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected static string $view = 'page-builder::filament.edit-with-builder';

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    // Le header Filament est masqué (l'éditeur unifié fournit sa propre topbar
    // avec titre/statut/aperçu/suppression). La sauvegarde et le flush cache
    // sont portés par le composant Livewire + l'observer du modèle Post.
    public function getBreadcrumbs(): array
    {
        return [];
    }
}
