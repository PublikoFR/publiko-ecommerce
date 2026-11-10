<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\Pages;

use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\JobStatus;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Support\AiPreflight;
use Pko\AiImporter\Support\ConfigColumnExtractor;
use Pko\AiImporter\Support\ConfigDiagnostics;
use Pko\AiImporter\Support\ImportFlow;

/**
 * « Nouvel import » — assistant en 2 étapes :
 *
 *  1. Fichier : configuration + fichier, contrôlés l'un contre l'autre. Une
 *     erreur bloquante (feuille absente, colonne de jointure introuvable,
 *     config sans identification) empêche de continuer.
 *  2. Réglages : lignes à traiter, colonnes à calculer (avec le nombre d'appels
 *     IA à prévoir), réglages avancés repliés.
 *
 * `?config=<id>` présélectionne une configuration (bouton « Nouvel import »
 * d'une configuration).
 */
class CreateImportJob extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ImportJobResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Nouvel import';
    }

    public function getBreadcrumb(): string
    {
        return 'Nouvel import';
    }

    /**
     * @return array<int, Step>
     */
    public function getSteps(): array
    {
        return [
            Step::make('Fichier')
                ->description('Configuration et fichier fournisseur')
                ->icon('heroicon-o-document-arrow-up')
                ->schema(ImportJobResource::fileStep())
                ->afterValidation(function (): void {
                    $report = ImportJobResource::preflight($this->data['config_id'] ?? null, $this->data['input_file_path'] ?? null);

                    if (ConfigDiagnostics::hasErrors($report['findings'])) {
                        Notification::make()
                            ->danger()
                            ->title('Le contrôle signale une erreur bloquante')
                            ->body('Corrigez la configuration ou changez de fichier : en l\'état, la préparation échouerait ou ne produirait rien.')
                            ->send();

                        throw new Halt;
                    }
                }),

            Step::make('Réglages')
                ->description('Lignes à traiter et colonnes à calculer')
                ->icon('heroicon-o-adjustments-horizontal')
                ->schema(ImportJobResource::settingsStep()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('configs')
                ->label('Configurations')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(ImporterConfigResource::getUrl('index')),
        ];
    }

    /**
     * Présélection d'une configuration passée en query string : ses colonnes
     * calculables sont cochées comme si elle venait d'être choisie.
     */
    protected function afterFill(): void
    {
        $config = ImporterConfig::query()->find(request()->integer('config'));
        if ($config === null) {
            return;
        }

        $this->data['config_id'] = $config->id;
        $this->data['options']['columns_to_process'] = ConfigColumnExtractor::allColumnKeys($config);
    }

    protected function getSubmitFormAction(): Actions\Action
    {
        return parent::getSubmitFormAction()
            ->label('Lancer la préparation')
            ->icon('heroicon-m-play');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] ??= (string) Str::uuid();
        $data['status'] ??= JobStatus::Pending->value;
        $data['import_status'] ??= ImportStatus::Pending->value;
        $data['created_by_id'] ??= Filament::auth()->id();

        // Une date d'import automatique posée dès la création = import programmé.
        if (! empty($data['scheduled_at'])) {
            $data['import_status'] = ImportStatus::Scheduled->value;
        }

        return $data;
    }

    /**
     * Bloque la création si la config utilise une IA inutilisable : la
     * préparation partirait et ses colonnes IA resteraient vides.
     */
    protected function beforeCreate(): void
    {
        $configId = $this->data['config_id'] ?? null;
        $columns = (array) ($this->data['options']['columns_to_process'] ?? []);

        if ($columns === [] && ConfigColumnExtractor::allColumnKeys(ImportJobResource::config($configId)) !== []) {
            Notification::make()->danger()->title('Aucune colonne à calculer')
                ->body('Cochez au moins une colonne : la préparation ne produirait rien.')->send();

            $this->halt();
        }

        if (! AiPreflight::allowsParse(ImportJobResource::config($configId), $columns)) {
            $this->halt();
        }
    }

    protected function afterCreate(): void
    {
        $started = ImportFlow::startParse($this->record);

        Notification::make()
            ->success()
            ->title('Préparation lancée')
            ->body($started
                ? 'Le fichier est en cours de préparation. Cette page se met à jour toute seule.'
                : 'Le fichier sera pris en charge dans les 2 minutes. Cette page se met à jour toute seule.')
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return ImportJobResource::getUrl('view', ['record' => $this->record]);
    }
}
