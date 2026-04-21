<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Pko\StorefrontCms\Filament\Pages\PkoMediaLibrary;
use Pko\StorefrontCms\Filament\Resources\HomeOfferResource;
use Pko\StorefrontCms\Filament\Resources\HomeSlideResource;
use Pko\StorefrontCms\Filament\Resources\HomeTileResource;
use Pko\StorefrontCms\Filament\Resources\NewsletterSubscriberResource;
use Pko\StorefrontCms\Filament\Resources\PostResource;
use Pko\StorefrontCms\Filament\Resources\PostTypeResource;

class StorefrontCmsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'storefront-cms';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                HomeSlideResource::class,
                HomeTileResource::class,
                HomeOfferResource::class,
                PostResource::class,
                PostTypeResource::class,
                NewsletterSubscriberResource::class,
            ])
            ->pages([
                PkoMediaLibrary::class,
            ])
            ->renderHook(
                'panels::body.end',
                function (): HtmlString {
                    // Sur la page Médiathèque, le composant est déjà monté par le shell Filament :
                    // on évite la double-instance (état dupliqué, URL conflicts).
                    if (request()->routeIs('filament.*.pages.mediatheque')) {
                        return new HtmlString('');
                    }

                    return new HtmlString(Blade::render('<livewire:pko-media-library />'));
                },
            );
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
