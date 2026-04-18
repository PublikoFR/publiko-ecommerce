<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Filament\Widgets\ImportJobProgressWidget;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Services\LunarBackupManager;

class ViewImportJob extends ViewRecord
{
    protected static string $resource = ImportJobResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ImportJobProgressWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

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
            Actions\Action::make('resumeImport')
                ->label('Reprendre l\'import')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->import_status, [ImportStatus::Error], true)
                    && $this->record->status === JobStatus::Parsed)
                ->requiresConfirmation()
                ->modalDescription('Relance l\'import sur les lignes staging restantes (status=pending|validated|warning). Les lignes déjà importées sont ignorées.')
                ->action(function (): void {
                    ImportStagingToLunarJob::dispatch($this->record->id)
                        ->onQueue(config('ai-importer.queues.import', 'ai-importer-import'));
                    $this->record->update(['import_status' => ImportStatus::Pending, 'error_message' => null]);
                    Notification::make()->success()->title('Import relancé')->send();
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
