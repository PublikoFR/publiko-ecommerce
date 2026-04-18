<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Resources\NewsletterSubscriberResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Pko\StorefrontCms\Filament\Resources\NewsletterSubscriberResource;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;
}
