<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;

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
