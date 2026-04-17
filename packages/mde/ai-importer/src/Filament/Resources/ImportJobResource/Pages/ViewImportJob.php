<?php

declare(strict_types=1);

namespace Mde\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Mde\AiImporter\Enums\ImportStatus;
use Mde\AiImporter\Enums\JobStatus;
use Mde\AiImporter\Filament\Resources\ImportJobResource;
use Mde\AiImporter\Jobs\ImportStagingToLunarJob;
use Mde\AiImporter\Jobs\ParseFileToStagingJob;
use Mde\AiImporter\Services\LunarBackupManager;

class ViewImportJob extends ViewRecord
{
    protected static string $resource = ImportJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resumeParse')
                ->label('Relancer le parse')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->status, [JobStatus::Paused, JobStatus::Error], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    ParseFileToStagingJob::dispatch($this->record->id)
                        ->onQueue(config('ai-importer.queues.parse', 'ai-importer-parse'));
                    Notification::make()->success()->title('Parse relancé en file d\'attente')->send();
                }),
            Actions\Action::make('launchImport')
                ->label('Lancer l\'import Lunar')
                ->icon('heroicon-o-arrow-down-on-square')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === JobStatus::Parsed
                    && in_array($this->record->import_status, [ImportStatus::Pending, ImportStatus::Scheduled], true))
                ->requiresConfirmation()
                ->modalDescription('Les produits seront créés / mis à jour dans Lunar. Un snapshot de sauvegarde est pris avant d\'écrire.')
                ->action(function (): void {
                    ImportStagingToLunarJob::dispatch($this->record->id)
                        ->onQueue(config('ai-importer.queues.import', 'ai-importer-import'));
                    Notification::make()->success()->title('Import Lunar mis en file')->send();
                }),
            Actions\Action::make('rollback')
                ->label('Rollback')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => $this->record->import_status === ImportStatus::Imported
                    && $this->record->backup_path !== null
                    && ! $this->record->rollback_completed)
                ->requiresConfirmation()
                ->modalDescription('Restaure les produits Lunar à partir du snapshot. Irréversible une fois lancé.')
                ->action(function (LunarBackupManager $backup): void {
                    $backup->restore($this->record);
                    Notification::make()->warning()->title('Rollback exécuté')->send();
                    $this->record->update(['import_status' => ImportStatus::RolledBack]);
                }),
            Actions\Action::make('cancel')
                ->label('Annuler')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn (): bool => in_array($this->record->status, [JobStatus::Pending, JobStatus::Parsing, JobStatus::Paused], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => JobStatus::Cancelled,
                        'stopped_by_user' => true,
                    ]);
                    Notification::make()->success()->title('Job annulé')->send();
                }),
        ];
    }
}
