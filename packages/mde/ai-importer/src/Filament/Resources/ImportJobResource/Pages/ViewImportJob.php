<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Mde\AiImporter\Filament\Resources\ImportJobResource;

class ViewImportJob extends ViewRecord
{
    protected static string $resource = ImportJobResource::class;

    // Phase 4 : RelationManagers staging + logs, actions launch import + rollback, polling progress.
}
