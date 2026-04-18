<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Shim du plugin tomatophp/filament-media-manager : expose l'id et les
 * propriétés publiques attendues par les classes vendor
 * (Folder::boot, FolderResource form, etc.) SANS enregistrer les resources
 * FolderResource/MediaResource. L'UX médias passe par notre PkoMediaLibrary.
 */
class MediaManagerShimPlugin implements Plugin
{
    public bool $allowSubFolders = false;

    public bool $allowUserAccess = false;

    public function getId(): string
    {
        return 'filament-media-manager';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return new static;
    }
}
