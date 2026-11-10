<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource;
use Pko\AiImporter\Filament\Resources\ImportJobResource;
use Pko\AiImporter\Models\ImporterConfig;

/**
 * @property ImporterConfig $record
 */
class EditImporterConfig extends EditRecord
{
    protected static string $resource = ImporterConfigResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Étape suivante du parcours : utiliser cette configuration.
            Actions\Action::make('newImport')
                ->label('Nouvel import')
                ->icon('heroicon-m-play')
                ->tooltip('Préparer un fichier avec cette configuration (enregistrez d\'abord vos modifications)')
                ->url(fn (): string => ImportJobResource::getUrl('create', ['config' => $this->record->id])),

            Actions\ActionGroup::make([
                Actions\Action::make('export')
                    ->label('Exporter (JSON)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => ImporterConfigResource::exportJson($this->record)),
                Actions\Action::make('duplicate')
                    ->label('Dupliquer')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (): void {
                        $copy = $this->record->replicate();
                        $copy->name = ImporterConfigResource::copyName($this->record->name);
                        $copy->save();

                        Notification::make()->success()->title('Configuration dupliquée')->send();
                        $this->redirect(ImporterConfigResource::getUrl('edit', ['record' => $copy]));
                    }),
                Actions\DeleteAction::make(),
            ])
                ->label('Plus')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ImporterConfigResource::hydrateVisual($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ImporterConfigResource::dehydrateVisual($data);
    }

    /**
     * Rester sur la page après enregistrement, et recharger l'état depuis la
     * base : le bandeau de contrôle reflète alors la configuration enregistrée.
     */
    protected function afterSave(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }
}
