<?php

declare(strict_types=1);

namespace Pko\AiImporter\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Models\ImportJob;

/**
 * Dispatches Filament in-app notifications to the staff member who kicked
 * off an import (`ImportJob::created_by_id`). No-ops cleanly when no
 * creator is attached (e.g. imports triggered via `ai-importer:run-scheduled`
 * on behalf of nobody), when the staff doesn't ship a `notify()` method, or
 * when the application has no `notifications` table (database notifications
 * not enabled on the panel) — the import screen itself always shows the outcome.
 *
 * Email / external channels are intentionally deferred — Filament's DB
 * notification channel covers the 90% case and keeps the package free of
 * Mailable / Markdown template scaffolding.
 */
final class ImportJobNotifier
{
    public static function parseCompleted(ImportJob $job): void
    {
        self::send(
            $job,
            Notification::make()
                ->title('Préparation terminée')
                ->body(sprintf('%s : %s ligne(s) à vérifier avant import.', $job->config?->name ?? 'Job', $job->staging_count))
                ->success()
                ->icon('heroicon-o-document-check')
                ->actions([
                    Action::make('view')
                        ->label('Voir')
                        ->url(ImportJobResource::getUrl('view', ['record' => $job->id])),
                ]),
        );
    }

    public static function importCompleted(ImportJob $job): void
    {
        $hasErrors = $job->error_count > 0;

        self::send(
            $job,
            Notification::make()
                ->title($hasErrors ? 'Import terminé avec des erreurs' : 'Import terminé')
                ->body(sprintf(
                    '%s : %s créés/modifiés%s',
                    $job->config?->name ?? 'Job',
                    $job->imported_count,
                    $hasErrors ? ' · '.$job->error_count.' erreurs' : '',
                ))
                ->color($hasErrors ? 'warning' : 'success')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->actions([
                    Action::make('view')
                        ->label('Voir')
                        ->url(ImportJobResource::getUrl('view', ['record' => $job->id])),
                ]),
        );
    }

    public static function importFailed(ImportJob $job, string $error): void
    {
        self::send(
            $job,
            Notification::make()
                ->title('Import interrompu')
                ->body($error)
                ->danger()
                ->icon('heroicon-o-exclamation-triangle'),
        );
    }

    private static ?bool $tableExists = null;

    private static function databaseNotificationsAvailable(): bool
    {
        return self::$tableExists ??= Schema::hasTable('notifications');
    }

    /** Oublie le résultat mémorisé (tests, migration jouée en cours de process). */
    public static function flush(): void
    {
        self::$tableExists = null;
    }

    private static function send(ImportJob $job, Notification $notification): void
    {
        // Les notifications en base de Filament partent en file d'attente : sans la
        // table `notifications` (panneau sans `databaseNotifications()`), chaque fin
        // d'import laisserait un job en échec. On n'envoie que si elle existe.
        if (! self::databaseNotificationsAvailable()) {
            return;
        }

        $recipient = $job->createdBy()->first();
        if (! $recipient) {
            return;
        }

        try {
            $notification->sendToDatabase($recipient);
        } catch (\Throwable) {
            // Silent: the staff model may not implement Notifiable in all setups;
            // we don't want to fail the job just because we couldn't notify.
        }
    }
}
