<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Filament\Resources\MailTemplateResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Validation\ValidationException;
use Pko\MailTemplates\Filament\Resources\MailTemplateResource;
use Pko\MailTemplates\Support\ContentGuardException;

/**
 * Écran d'édition d'un modèle d'e-mail.
 *
 * Le form Filament ne porte plus que les réglages (objet, activation) : le
 * contenu est édité par le composant PageBuilder, le même que celui des pages
 * et articles, qui écrit directement la colonne `content`.
 *
 * La cohérence des placeholders n'est donc plus contrôlée ici mais dans
 * `MailTemplate::saving()`, seul point de passage commun aux deux éditeurs.
 */
class EditMailTemplate extends EditRecord
{
    protected static string $resource = MailTemplateResource::class;

    protected static string $view = 'pko-mail-templates::filament.edit-mail-template';

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * Désactiver un modèle dont le contenu est incomplet doit rester possible ;
     * l'activer avec un contenu incomplet ne l'est pas. C'est `ContentGuard`,
     * appelé par le modèle, qui tranche — on se contente ici de traduire son
     * refus en erreur de formulaire plutôt qu'en page d'erreur 500.
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (ContentGuardException $e) {
            Notification::make()->danger()->title($e->getMessage())->persistent()->send();

            throw ValidationException::withMessages(['data.subject' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
