<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Pko\AiImporter\Enums\FlowState;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\LogPhase;
use Pko\AiImporter\Enums\RowFilter;
use Pko\AiImporter\Enums\UpdateMode;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Services\LunarBackupManager;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\AiImporter\Support\AiPreflight;
use Pko\AiImporter\Support\ImportFlow;
use Pko\AiImporter\Support\ProductFieldCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Écran d'un import. Il suit le parcours Préparation → Vérification → Import →
 * Terminé ({@see FlowState}) et ne propose qu'UNE action principale, celle de
 * l'étape en cours ; tout le reste est dans le menu « Plus ».
 *
 *  - bandeau d'étape : où on en est, ce qu'il se passe, ce qu'il faut faire ;
 *  - lignes préparées (relation manager) : vérifier, corriger, écarter ;
 *  - journal et détails : repliés tant que tout va bien.
 *
 * La page se rafraîchit seule tant qu'un traitement tourne (`wire:poll` du
 * bandeau), sans widget ni requête supplémentaire.
 *
 * @property ImportJob $record
 */
class ViewImportJob extends ViewRecord
{
    /** Événement Livewire : l'état de l'import a pu changer, les tableaux se rechargent. */
    public const FLOW_UPDATED = 'import-flow-updated';

    protected static string $resource = ImportJobResource::class;

    public function getTitle(): string
    {
        return 'Import #'.$this->record->id.' · '.$this->record->displayName();
    }

    public function getBreadcrumb(): string
    {
        return '#'.$this->record->id;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()
                ->schema([
                    ViewEntry::make('flow')
                        ->hiddenLabel()
                        ->view('pko-ai-importer::filament.infolists.flow-header'),
                ]),

            Section::make('En direct')
                ->icon('heroicon-o-command-line')
                ->description('Ce que le traitement fait, ligne par ligne.')
                ->collapsible()
                // Affiché quand il y a quelque chose à suivre ou à comprendre (traitement
                // en cours, arrêt sur erreur). Le reste du temps, l'onglet « Journal
                // détaillé » sous les lignes contient tout, avec filtres.
                ->visible(fn (ImportJob $record): bool => $record->flowState()->isRunning() || $record->flowState()->isFailure())
                ->schema([
                    Tabs::make('console_phases')
                        // Onglet Import ouvert d'office dès qu'un import a démarré.
                        ->activeTab(fn (ImportJob $record): int => $record->import_started_at !== null
                            || in_array($record->import_status, [ImportStatus::Queued, ImportStatus::Importing], true) ? 2 : 1)
                        ->tabs(array_map(
                            fn (LogPhase $phase): Tabs\Tab => Tabs\Tab::make($phase->label())
                                ->badge(fn (ImportJob $record): int => static::phaseLogs($record, $phase)->count())
                                ->schema([
                                    ViewEntry::make("console_logs_{$phase->value}")
                                        ->hiddenLabel()
                                        ->view('pko-ai-importer::filament.infolists.console-logs', ['phase' => $phase]),
                                ]),
                            LogPhase::cases(),
                        )),
                ]),

            Section::make('Détails')
                ->icon('heroicon-o-information-circle')
                ->description('Fichier, configuration, réglages et sauvegarde.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    ViewEntry::make('details')
                        ->hiddenLabel()
                        ->view('pko-ai-importer::filament.infolists.job-details'),
                ]),
        ]);
    }

    /**
     * Tic de rafraîchissement (`wire:poll` du bandeau, tant qu'un traitement
     * tourne) et fin de chaque action : la page se redessine, et les tableaux
     * sous elle (lignes préparées, journal) — des composants Livewire distincts,
     * qui ne suivent pas le rendu de la page — sont prévenus de se recharger.
     * Un seul minuteur pour tout l'écran.
     */
    public function refreshFlow(): void
    {
        $this->dispatch(self::FLOW_UPDATED);
    }

    /**
     * Les logs antérieurs à la colonne `phase` (NULL) sont rangés côté préparation.
     *
     * @return HasMany<ImportLog>
     */
    public static function phaseLogs(ImportJob $record, LogPhase $phase): HasMany
    {
        return $record->logs()->where(fn ($q) => $phase === LogPhase::Parse
            ? $q->where('phase', $phase->value)->orWhereNull('phase')
            : $q->where('phase', $phase->value));
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(),
            $this->resumeParseAction(),
            $this->startNowAction(),
            $this->cancelScheduleAction(),
            $this->cancelParseAction(),
            $this->retryErrorsAction(),
            $this->rollbackAction(),

            Actions\ActionGroup::make([
                $this->importSettingsAction(),
                Actions\Action::make('downloadSource')
                    ->label('Télécharger le fichier source')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => $this->fileExists($this->record->input_file_path))
                    ->action(fn (): StreamedResponse => $this->download($this->record->input_file_path)),
                Actions\Action::make('downloadBackup')
                    ->label('Télécharger la sauvegarde')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn (): bool => $this->fileExists($this->record->backup_path))
                    ->action(fn (): StreamedResponse => $this->download($this->record->backup_path)),
                Actions\Action::make('runScheduler')
                    ->label('Lancer le planificateur maintenant')
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->modalDescription('Démarre sans attendre les préparations en file d\'attente et les imports programmés arrivés à échéance.')
                    ->modalSubmitActionLabel('Lancer')
                    ->action(function (): void {
                        Artisan::call('ai-importer:run-scheduled');
                        Notification::make()->success()->title('Planificateur exécuté')
                            ->body(trim(Artisan::output()) ?: 'Rien à démarrer.')->send();
                    }),
                Actions\Action::make('testScheduler')
                    ->label('Vérifier le planificateur')
                    ->icon('heroicon-o-clock')
                    ->action(function (): void {
                        Artisan::call('ai-importer:run-scheduled', ['--dry' => true]);
                        Notification::make()->info()->title('Ce que le planificateur démarrerait')
                            ->body(trim(Artisan::output()) ?: 'Rien à démarrer.')->send();
                    }),
                Actions\DeleteAction::make()
                    ->label('Supprimer l\'import')
                    ->modalDescription('Supprime cet import, ses lignes préparées et son journal. Les produits déjà importés dans le catalogue ne sont pas touchés.'),
            ])
                ->label('Plus')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    // ── Action principale : écrire dans le catalogue ─────────────────────────

    private function importAction(): Actions\Action
    {
        return Actions\Action::make('launchImport')
            ->label(fn (): string => match ($this->record->flowState()) {
                FlowState::ImportFailed => 'Reprendre l\'import',
                FlowState::Done => 'Importer les lignes restantes',
                default => 'Importer dans le catalogue',
            })
            ->icon('heroicon-m-arrow-down-on-square')
            ->visible(fn (): bool => match ($this->record->flowState()) {
                FlowState::Review, FlowState::RolledBack, FlowState::ImportFailed => true,
                FlowState::Done => $this->record->remainingRows() > 0,
                default => false,
            })
            ->modalHeading('Importer dans le catalogue')
            ->modalDescription(fn (): string => $this->importSummary())
            ->modalSubmitActionLabel('Importer')
            ->modalWidth('2xl')
            ->fillForm(fn (): array => $this->importSettings() + ['when' => 'now'])
            ->form(fn (): array => [
                ...$this->importSettingsSchema(),
                Forms\Components\Radio::make('when')
                    ->label('Quand')
                    ->options(['now' => 'Maintenant', 'later' => 'Plus tard'])
                    ->inline()
                    ->live()
                    ->required(),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label('Date et heure')
                    ->seconds(false)
                    ->minDate(now())
                    ->visible(fn (Get $get): bool => $get('when') === 'later')
                    ->required(fn (Get $get): bool => $get('when') === 'later'),
            ])
            ->action(function (array $data): void {
                if ($this->record->remainingRows() === 0) {
                    Notification::make()->warning()->title('Aucune ligne à importer')
                        ->body('Toutes les lignes sont déjà importées, écartées ou en erreur.')->send();

                    return;
                }

                $this->saveImportSettings($data);

                $at = ($data['when'] ?? 'now') === 'later' && ! empty($data['scheduled_at'])
                    ? Carbon::parse($data['scheduled_at'])
                    : null;

                $notification = match (ImportFlow::startImport($this->record, $at)) {
                    'scheduled' => ['Import programmé', 'Il démarrera le '.$at?->format('d/m/Y à H:i').'.'],
                    'soon' => ['Import lancé', 'Il démarre dans les 2 minutes. Une sauvegarde est prise avant la première écriture.'],
                    default => ['Import lancé', 'Une sauvegarde est prise avant la première écriture. Cette page se met à jour toute seule.'],
                };

                Notification::make()->success()->title($notification[0])->body($notification[1])->send();
                $this->refreshFlow();
            });
    }

    private function importSummary(): string
    {
        $counts = $this->record->stagingStatusCounts();
        $ready = $counts['pending'] + $counts['warning'];

        $parts = [$ready.' ligne'.($ready > 1 ? 's' : '').' à importer'];
        if ($counts['skipped'] > 0) {
            $parts[] = $counts['skipped'].' écartée(s)';
        }
        if ($counts['error'] > 0) {
            $parts[] = $counts['error'].' en erreur (non importées)';
        }

        return implode(' · ', $parts).'. Une sauvegarde des produits concernés est prise avant d\'écrire : l\'import reste annulable.';
    }

    /**
     * Réglages de l'écriture catalogue, partagés par « Importer » et « Réglages d'import ».
     *
     * @return array<int, Forms\Components\Component>
     */
    private function importSettingsSchema(): array
    {
        return [
            Forms\Components\Radio::make('update_mode')
                ->label('Produits déjà au catalogue')
                ->options(collect(UpdateMode::cases())->mapWithKeys(fn (UpdateMode $m): array => [$m->value => $m->label()])->all())
                ->descriptions([
                    UpdateMode::All->value => 'Tous les champs préparés remplacent ceux du catalogue.',
                    UpdateMode::Price->value => 'Prix, prix d\'achat et éco-participation seulement.',
                    UpdateMode::Stock->value => 'Quantité en stock seulement.',
                    UpdateMode::PriceStock->value => 'Prix et stock, rien d\'autre.',
                ])
                ->helperText('Un nouveau produit est toujours créé avec tous ses champs.')
                ->required(),

            Forms\Components\Select::make('row_filter')
                ->label('Produits concernés')
                ->options(collect(RowFilter::cases())->mapWithKeys(fn (RowFilter $f): array => [$f->value => $f->label()])->all())
                ->selectablePlaceholder(false)
                ->required(),

            Forms\Components\Select::make('join_column')
                ->label('Reconnaître un produit existant par')
                ->options(ImportJobResource::joinColumnOptions())
                ->selectablePlaceholder(false)
                ->required(),

            Forms\Components\Toggle::make('limit_columns')
                ->label('N\'écrire que certains champs')
                ->helperText('Désactivé : tous les champs préparés sont écrits.')
                ->live(),

            Forms\Components\CheckboxList::make('columns_to_import')
                ->label('Champs à écrire')
                ->helperText('La référence et le nom servent toujours à identifier le produit. L\'éco-participation suit toujours le prix.')
                ->options(fn (): array => $this->stagingFieldOptions())
                ->columns(2)
                ->bulkToggleable()
                ->visible(fn (Get $get): bool => (bool) $get('limit_columns'))
                ->required(fn (Get $get): bool => (bool) $get('limit_columns')),

            Forms\Components\Select::make('error_policy')
                ->label('En cas d\'erreur sur une ligne')
                ->options(ImportJobResource::errorPolicyOptions())
                ->selectablePlaceholder(false)
                ->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function importSettings(): array
    {
        $options = (array) ($this->record->options ?? []);
        $columns = array_values((array) ($options['columns_to_import'] ?? []));
        $join = (string) ($options['join_column'] ?? 'reference');

        return [
            'update_mode' => (UpdateMode::tryFrom((string) ($options['update_mode'] ?? '')) ?? UpdateMode::All)->value,
            'row_filter' => (RowFilter::tryFrom((string) ($options['row_filter'] ?? '')) ?? RowFilter::All)->value,
            'join_column' => in_array($join, ['ean', 'ean13'], true) ? 'ean' : 'reference',
            'limit_columns' => $columns !== [],
            'columns_to_import' => $columns,
            'error_policy' => $this->record->error_policy->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveImportSettings(array $data): void
    {
        $options = (array) ($this->record->options ?? []);
        $options['update_mode'] = $data['update_mode'];
        $options['row_filter'] = $data['row_filter'];
        $options['join_column'] = $data['join_column'];
        $options['columns_to_import'] = ! empty($data['limit_columns'])
            ? array_values((array) ($data['columns_to_import'] ?? []))
            : [];

        $this->record->update([
            'options' => $options,
            'error_policy' => $data['error_policy'],
        ]);
    }

    /**
     * Champs réellement présents dans les lignes préparées (clés canoniques
     * après normalisation des alias), libellés — plutôt que tout le catalogue
     * de champs, dont la plupart ne concernent pas ce fichier.
     *
     * @return array<string, string>
     */
    private function stagingFieldOptions(): array
    {
        $first = $this->record->stagingRecords()->orderBy('row_number')->first(['data']);
        if ($first === null) {
            return ProductFieldCatalog::flat();
        }

        $data = $first->data instanceof \ArrayObject ? $first->data->getArrayCopy() : (array) $first->data;
        $keys = array_keys(LunarProductWriter::normalizeLegacyKeys($data));

        $options = [];
        foreach (array_intersect($keys, LunarProductWriter::CANONICAL_KEYS) as $key) {
            $options[$key] = ProductFieldCatalog::label($key);
        }

        return $options !== [] ? $options : ProductFieldCatalog::flat();
    }

    private function importSettingsAction(): Actions\Action
    {
        return Actions\Action::make('editOptions')
            ->label('Réglages d\'import')
            ->icon('heroicon-o-adjustments-horizontal')
            ->visible(fn (): bool => in_array($this->record->flowState(), [
                FlowState::Review, FlowState::Scheduled, FlowState::ImportFailed, FlowState::RolledBack, FlowState::Done,
            ], true))
            ->modalHeading('Réglages d\'import')
            ->modalDescription('S\'appliquent au prochain lancement de l\'import.')
            ->modalSubmitActionLabel('Enregistrer')
            ->modalWidth('2xl')
            ->fillForm(fn (): array => $this->importSettings())
            ->form(fn (): array => $this->importSettingsSchema())
            ->action(function (array $data): void {
                $this->saveImportSettings($data);
                Notification::make()->success()->title('Réglages enregistrés')->send();
            });
    }

    // ── Préparation ──────────────────────────────────────────────────────────

    private function resumeParseAction(): Actions\Action
    {
        return Actions\Action::make('resumeParse')
            ->label('Relancer la préparation')
            ->icon('heroicon-m-arrow-path')
            ->visible(fn (): bool => in_array($this->record->flowState(), [FlowState::PrepareFailed, FlowState::Cancelled], true)
                && $this->record->config_id !== null)
            ->requiresConfirmation()
            ->modalDescription('La préparation reprend après la dernière ligne traitée : les lignes déjà préparées sont conservées.')
            ->modalSubmitActionLabel('Relancer')
            ->action(function (): void {
                if (! AiPreflight::allowsParse($this->record->config, (array) ($this->record->options['columns_to_process'] ?? []))) {
                    return;
                }

                $started = ImportFlow::startParse($this->record);
                Notification::make()->success()->title('Préparation relancée')
                    ->body($started ? null : 'Elle reprend dans les 2 minutes.')->send();
                $this->refreshFlow();
            });
    }

    private function cancelParseAction(): Actions\Action
    {
        return Actions\Action::make('cancel')
            ->label('Arrêter la préparation')
            ->icon('heroicon-m-stop')
            ->color('gray')
            ->visible(fn (): bool => in_array($this->record->flowState(), [FlowState::Waiting, FlowState::Preparing], true))
            ->requiresConfirmation()
            ->modalDescription('Les lignes déjà préparées sont conservées. Vous pourrez relancer la préparation là où elle s\'est arrêtée.')
            ->modalSubmitActionLabel('Arrêter')
            ->action(function (): void {
                ImportFlow::cancelParse($this->record);
                Notification::make()->success()->title('Préparation arrêtée')->send();
                $this->refreshFlow();
            });
    }

    // ── Import programmé ─────────────────────────────────────────────────────

    private function startNowAction(): Actions\Action
    {
        return Actions\Action::make('startNow')
            ->label('Importer maintenant')
            ->icon('heroicon-m-arrow-down-on-square')
            ->visible(fn (): bool => $this->record->flowState() === FlowState::Scheduled
                && (bool) $this->record->scheduled_at?->isFuture())
            ->requiresConfirmation()
            ->modalDescription(fn (): string => $this->importSummary())
            ->modalSubmitActionLabel('Importer')
            ->action(function (): void {
                ImportFlow::startImport($this->record);
                Notification::make()->success()->title('Import lancé')->send();
                $this->refreshFlow();
            });
    }

    private function cancelScheduleAction(): Actions\Action
    {
        return Actions\Action::make('cancelSchedule')
            ->label('Annuler la programmation')
            ->icon('heroicon-m-x-mark')
            ->color('gray')
            ->visible(fn (): bool => $this->record->flowState() === FlowState::Scheduled)
            ->requiresConfirmation()
            ->modalDescription('L\'import ne démarrera plus tout seul. Les lignes préparées restent à vérifier.')
            ->modalSubmitActionLabel('Annuler la programmation')
            ->action(function (): void {
                ImportFlow::cancelSchedule($this->record);
                Notification::make()->success()->title('Programmation annulée')->send();
                $this->refreshFlow();
            });
    }

    // ── Après l'import ───────────────────────────────────────────────────────

    private function retryErrorsAction(): Actions\Action
    {
        return Actions\Action::make('retryErrors')
            ->label('Réessayer les lignes en erreur')
            ->icon('heroicon-m-arrow-uturn-right')
            ->color('gray')
            ->visible(fn (): bool => in_array($this->record->flowState(), [FlowState::Done, FlowState::ImportFailed], true)
                && $this->record->stagingRecords()->where('status', 'error')->exists())
            ->requiresConfirmation()
            ->modalDescription('Les lignes en erreur repassent « à importer ». Corrigez-les si besoin, puis relancez l\'import : les lignes déjà importées ne sont pas rejouées.')
            ->modalSubmitActionLabel('Remettre à importer')
            ->action(function (): void {
                $count = ImportFlow::retryErrorRows($this->record);
                Notification::make()->success()->title($count.' ligne(s) remise(s) à importer')->send();
                $this->refreshFlow();
            });
    }

    private function rollbackAction(): Actions\Action
    {
        return Actions\Action::make('rollback')
            ->label('Annuler l\'import')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('danger')
            ->outlined()
            ->visible(fn (): bool => in_array($this->record->flowState(), [FlowState::Done, FlowState::ImportFailed], true)
                && $this->fileExists($this->record->backup_path)
                && ! $this->record->rollback_completed)
            ->requiresConfirmation()
            ->modalHeading('Annuler l\'import')
            ->modalDescription('Le catalogue revient à son état d\'avant l\'import : les produits modifiés retrouvent leurs anciennes valeurs, les produits créés sont supprimés (ou passés en brouillon s\'ils sont déjà dans une commande ou un panier). Les lignes préparées repassent « à importer ».')
            ->modalSubmitActionLabel('Annuler l\'import')
            ->action(function (LunarBackupManager $backup): void {
                $backup->restore($this->record);
                $this->record->update(['import_status' => ImportStatus::RolledBack, 'rollback_completed' => true]);
                Notification::make()->success()->title('Import annulé')
                    ->body('Le catalogue est revenu à son état d\'avant l\'import.')->send();
                $this->refreshFlow();
            });
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
}
