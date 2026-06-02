<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Enums\ErrorPolicy;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Enums\UpdateMode;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Filament\Widgets\ImportJobProgressWidget;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Jobs\ParseFileToStagingJob;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Services\LunarBackupManager;
use Pko\AiImporter\Support\ProductFieldCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Fichiers joints')
                ->icon('heroicon-o-paper-clip')
                ->collapsible()
                ->schema([
                    ViewEntry::make('attached_files')
                        ->view('pko-ai-importer::filament.infolists.attached-files'),
                    InfolistActions::make([
                        InfolistActions\Action::make('downloadSource')
                            ->label('Fichier source')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('gray')
                            ->visible(fn (ImportJob $record): bool => $this->fileExists($record->input_file_path))
                            ->action(fn (ImportJob $record) => $this->download($record->input_file_path)),
                        InfolistActions\Action::make('downloadBackup')
                            ->label('Sauvegarde')
                            ->icon('heroicon-o-archive-box-arrow-down')
                            ->color('gray')
                            ->visible(fn (ImportJob $record): bool => $this->fileExists($record->backup_path))
                            ->action(fn (ImportJob $record) => $this->download($record->backup_path)),
                        InfolistActions\Action::make('restoreBackup')
                            ->label('Restaurer')
                            ->icon('heroicon-o-arrow-uturn-left')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalDescription('Restaure les produits Lunar depuis la sauvegarde active. Irréversible.')
                            ->visible(fn (ImportJob $record): bool => $this->fileExists($record->backup_path))
                            ->action(function (ImportJob $record, LunarBackupManager $backup): void {
                                $backup->restore($record);
                                $record->update(['import_status' => ImportStatus::RolledBack]);
                                Notification::make()->warning()->title('Sauvegarde restaurée')->send();
                            }),
                    ]),
                ]),

            Section::make('Options d\'import')
                ->icon('heroicon-o-adjustments-horizontal')
                ->collapsible()
                ->columns(2)
                ->schema([
                    TextEntry::make('options.join_column')
                        ->label('Colonne de jointure')
                        ->state(fn (ImportJob $record): string => (string) ($record->options['join_column'] ?? 'reference'))
                        ->badge(),
                    TextEntry::make('options.update_mode')
                        ->label('Si le produit existe déjà')
                        ->state(fn (ImportJob $record): string => (
                            UpdateMode::tryFrom((string) ($record->options['update_mode'] ?? 'all')) ?? UpdateMode::All
                        )->label())
                        ->badge(),
                    TextEntry::make('error_policy')
                        ->label('Politique d\'erreur')
                        ->state(fn (ImportJob $record): string => match ($record->error_policy) {
                            ErrorPolicy::Stop => 'Arrêter l\'import',
                            ErrorPolicy::Rollback => 'Rollback complet',
                            default => 'Ignorer et continuer',
                        })
                        ->badge(),
                    TextEntry::make('scheduled_at')
                        ->label('Planification')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('Immédiat'),
                    TextEntry::make('options.columns_to_import')
                        ->label('Colonnes à importer')
                        ->columnSpanFull()
                        ->state(function (ImportJob $record): string {
                            $cols = (array) ($record->options['columns_to_import'] ?? []);
                            if ($cols === []) {
                                return 'Toutes les colonnes mappées';
                            }
                            $labels = ProductFieldCatalog::flat();

                            return collect($cols)->map(fn ($c): string => $labels[$c] ?? (string) $c)->implode(', ');
                        }),
                ]),

            Section::make('Logs de console')
                ->icon('heroicon-o-command-line')
                ->collapsible()
                ->schema([
                    ViewEntry::make('console_logs')
                        ->view('pko-ai-importer::filament.infolists.console-logs'),
                ]),
        ]);
    }

    private function fileExists(?string $path): bool
    {
        return $path !== null
            && Storage::disk(config('ai-importer.storage.disk', 'local'))->exists($path);
    }

    private function download(string $path): StreamedResponse
    {
        return Storage::disk(config('ai-importer.storage.disk', 'local'))->download($path);
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
            Actions\Action::make('editOptions')
                ->label('Options d\'import')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (): bool => $this->record->import_status !== ImportStatus::Imported)
                ->fillForm(fn (): array => [
                    'join_column' => (string) ($this->record->options['join_column'] ?? 'reference'),
                    'update_mode' => (string) ($this->record->options['update_mode'] ?? UpdateMode::All->value),
                    'columns_to_import' => (array) ($this->record->options['columns_to_import'] ?? []),
                    'error_policy' => $this->record->error_policy->value,
                    'scheduled_at' => $this->record->scheduled_at,
                ])
                ->form([
                    Forms\Components\TextInput::make('join_column')
                        ->label('Colonne de jointure')
                        ->helperText('Champ utilisé pour retrouver un produit existant (ex. reference, sku, ean).')
                        ->default('reference')
                        ->required(),
                    Forms\Components\Radio::make('update_mode')
                        ->label('Si le produit existe déjà')
                        ->options(collect(UpdateMode::cases())
                            ->mapWithKeys(fn (UpdateMode $m): array => [$m->value => $m->label()])
                            ->toArray())
                        ->default(UpdateMode::All->value)
                        ->required(),
                    Forms\Components\Select::make('error_policy')
                        ->label('Politique d\'erreur')
                        ->options([
                            'ignore' => 'Ignorer et continuer',
                            'stop' => 'Arrêter l\'import',
                            'rollback' => 'Rollback complet',
                        ])
                        ->required(),
                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Programmer l\'import')
                        ->helperText('Laisser vide pour un lancement manuel.'),
                    Forms\Components\CheckboxList::make('columns_to_import')
                        ->label('Colonnes à importer')
                        ->helperText('Aucune sélection = toutes les colonnes mappées sont écrites.')
                        ->options(ProductFieldCatalog::flat())
                        ->columns(3)
                        ->columnSpanFull()
                        ->bulkToggleable(),
                ])
                ->action(function (array $data): void {
                    $options = (array) ($this->record->options ?? []);
                    $options['join_column'] = $data['join_column'];
                    $options['update_mode'] = $data['update_mode'];
                    $options['columns_to_import'] = array_values($data['columns_to_import'] ?? []);

                    $this->record->update([
                        'options' => $options,
                        'error_policy' => $data['error_policy'],
                        'scheduled_at' => $data['scheduled_at'],
                    ]);
                    Notification::make()->success()->title('Options enregistrées')->send();
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
                    $this->record->update(['import_status' => ImportStatus::Queued]);
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
                    $this->record->update(['import_status' => ImportStatus::Queued, 'error_message' => null]);
                    Notification::make()->success()->title('Import relancé')->send();
                }),
            Actions\Action::make('testCron')
                ->label('Tester CRON')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->tooltip('Exécute la commande planifiée en mode dry-run (aucun import dispatché).')
                ->action(function (): void {
                    Artisan::call('ai-importer:run-scheduled', ['--dry' => true]);
                    Notification::make()
                        ->info()
                        ->title('Test CRON (dry-run)')
                        ->body(trim(Artisan::output()) ?: 'Aucun job programmé dû.')
                        ->send();
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
