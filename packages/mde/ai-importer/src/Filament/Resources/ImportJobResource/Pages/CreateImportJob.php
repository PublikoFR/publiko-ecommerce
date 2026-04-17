<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Mde\AiImporter\Enums\ImportStatus;
use Mde\AiImporter\Enums\JobStatus;
use Mde\AiImporter\Filament\Resources\ImportJobResource;
use Mde\AiImporter\Jobs\ParseFileToStagingJob;

class CreateImportJob extends CreateRecord
{
    protected static string $resource = ImportJobResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] ??= (string) Str::uuid();
        $data['status'] ??= JobStatus::Pending->value;
        $data['import_status'] ??= ImportStatus::Pending->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        ParseFileToStagingJob::dispatch($this->record->id)
            ->onQueue(config('ai-importer.queues.parse', 'ai-importer-parse'));
    }
}
