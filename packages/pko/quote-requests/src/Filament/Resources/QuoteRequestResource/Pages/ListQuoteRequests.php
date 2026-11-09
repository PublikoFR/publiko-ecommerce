<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Filament\Resources\QuoteRequestResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Pko\QuoteRequests\Filament\Resources\QuoteRequestResource;

class ListQuoteRequests extends ListRecords
{
    protected static string $resource = QuoteRequestResource::class;
}
