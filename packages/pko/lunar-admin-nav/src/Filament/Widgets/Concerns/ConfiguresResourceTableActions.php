<?php

declare(strict_types=1);

namespace Pko\AdminNav\Filament\Widgets\Concerns;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Rebranche les actions de ligne d'une table de Resource affichée dans un widget de hub.
 *
 * Hors d'une page de Resource, Filament ne configure plus `EditAction` :
 * ni URL vers la page d'édition, ni formulaire — la modale s'ouvre vide.
 * On reproduit ici `ListRecords::configureEditAction()` : page d'édition si
 * la Resource en déclare une, sinon formulaire de la Resource en modale.
 */
trait ConfiguresResourceTableActions
{
    /**
     * @return class-string<\Filament\Resources\Resource>
     */
    abstract protected function getTableResource(): string;

    protected function configureTableAction(Action $action): void
    {
        $resource = $this->getTableResource();

        if ($action instanceof EditAction) {
            $action
                ->authorize(fn (Model $record): bool => $resource::canEdit($record))
                ->form(fn (Form $form): Form => $resource::form($form->columns(2)));

            if ($resource::hasPage('edit')) {
                $action->url(fn (Model $record): string => $resource::getUrl('edit', ['record' => $record]));
            }

            return;
        }

        if ($action instanceof DeleteAction) {
            $action->authorize(fn (Model $record): bool => $resource::canDelete($record));
        }
    }
}
