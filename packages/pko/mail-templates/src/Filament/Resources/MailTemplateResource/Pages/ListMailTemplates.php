<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Filament\Resources\MailTemplateResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Pko\MailTemplates\Filament\Resources\MailTemplateResource;

class ListMailTemplates extends ListRecords
{
    protected static string $resource = MailTemplateResource::class;
}
