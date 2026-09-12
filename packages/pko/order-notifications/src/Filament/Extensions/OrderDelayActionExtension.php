<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;
use Pko\OrderNotifications\Mail\OrderDelayedMail;
use Pko\OrderNotifications\Support\OrderMailData;

/**
 * E-mail 09 « Retard de commande », déclenché à la main depuis la fiche commande.
 *
 * Aucun statut Lunar ne représente un retard, et une date estimée ne peut pas
 * être devinée : c'est l'opérateur qui la saisit. Volontairement hors OnceMailer,
 * une commande pouvant subir plusieurs reports successifs.
 */
final class OrderDelayActionExtension extends ResourceExtension
{
    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, Action|ActionGroup>
     */
    public function headerActions(array $actions): array
    {
        $actions[] = $this->notifyDelayAction();

        return $actions;
    }

    private function notifyDelayAction(): Action
    {
        return Action::make('notify_order_delay')
            ->label('Signaler un retard')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->visible(fn (): bool => ! in_array(
                $this->resolveOrder()?->status,
                ['delivered', 'cancelled', null],
                true,
            ))
            ->form([
                DatePicker::make('new_date')
                    ->label('Nouvelle date estimée')
                    ->helperText('Communiquée telle quelle au client.')
                    ->native(false)
                    ->minDate(now())
                    ->required(),
            ])
            ->requiresConfirmation()
            ->modalDescription('Un e-mail sera envoyé au client avec la nouvelle date estimée.')
            ->action(function (array $data): void {
                $order = $this->resolveOrder();

                if (! $order) {
                    return;
                }

                $recipient = OrderMailData::recipient($order);

                if ($recipient === null) {
                    Notification::make()
                        ->danger()
                        ->title('Aucun e-mail destinataire pour cette commande.')
                        ->send();

                    return;
                }

                $mail = new OrderDelayedMail($order, Carbon::parse($data['new_date']));

                if (! $mail->shouldSend()) {
                    Notification::make()
                        ->warning()
                        ->title('Modèle « Retard de commande » désactivé')
                        ->body('Activez-le depuis Paramètres → E-mails pour pouvoir notifier le client.')
                        ->send();

                    return;
                }

                Mail::to($recipient)->queue($mail);

                Notification::make()
                    ->success()
                    ->title('Client notifié du retard')
                    ->body("E-mail envoyé à {$recipient}.")
                    ->send();
            });
    }

    private function resolveOrder(): ?Order
    {
        return $this->caller?->record instanceof Order ? $this->caller->record : null;
    }
}
