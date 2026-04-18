<?php

declare(strict_types=1);

namespace Mde\StorefrontCms\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Mde\StorefrontCms\Filament\Pages\MdeMediaLibrary;
use Mde\StorefrontCms\Filament\Resources\HomeOfferResource;
use Mde\StorefrontCms\Filament\Resources\HomeSlideResource;
use Mde\StorefrontCms\Filament\Resources\HomeTileResource;
use Mde\StorefrontCms\Filament\Resources\NewsletterSubscriberResource;
use Mde\StorefrontCms\Filament\Resources\PageResource;
use Mde\StorefrontCms\Filament\Resources\PostResource;

class StorefrontCmsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'mde-storefront-cms';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                HomeSlideResource::class,
                HomeTileResource::class,
                HomeOfferResource::class,
                PostResource::class,
                PageResource::class,
                NewsletterSubscriberResource::class,
            ])
            ->pages([
                MdeMediaLibrary::class,
            ])
            ->renderHook(
                'panels::body.end',
                fn (): HtmlString => new HtmlString(Blade::render('<livewire:mde-media-picker-modal />')),
            );
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
