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
use Pko\StorefrontCms\Filament\Resources\PageResource;
use Pko\StorefrontCms\Filament\Resources\PostResource;

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
                PageResource::class,
                NewsletterSubscriberResource::class,
            ])
            ->pages([
                PkoMediaLibrary::class,
            ])
            ->renderHook(
                'panels::body.end',
                fn (): HtmlString => new HtmlString(Blade::render('<livewire:pko-media-picker-modal />')),
            );
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
