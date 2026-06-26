<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;
use Pko\ShippingCommon\Mail\QuotePaymentLinkMail;

final class OrderQuoteActionsExtension extends ResourceExtension
{
    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, Action|ActionGroup>
     */
    public function headerActions(array $actions): array
    {
        $actions[] = $this->sendQuotePaymentLinkAction();

        return $actions;
    }

    private function sendQuotePaymentLinkAction(): Action
    {
        return Action::make('send_quote_payment_link')
            ->label('Envoyer lien de paiement')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn (): bool => $this->resolveOrder()?->status === 'awaiting-quote')
            ->form([
                TextInput::make('transport_cents')
                    ->label('Frais de port (en centimes HT)')
                    ->helperText('Ex : 1490 pour 14,90 € HT')
                    ->integer()
                    ->minValue(0)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $order = $this->resolveOrder();
                if (! $order) {
                    return;
                }

                $transportCents = (int) $data['transport_cents'];

                $url = URL::signedRoute('pko.quote.pay', [
                    'order' => $order->id,
                    'transport_cents' => $transportCents,
                ], now()->addDays(7));

                $recipient = $order->shippingAddress?->contact_email
                    ?? $order->billingAddress?->contact_email
                    ?? $order->customer?->email;

                if (empty($recipient)) {
                    Notification::make()
                        ->danger()
                        ->title('Aucun e-mail destinataire trouvé pour cette commande.')
                        ->send();

                    return;
                }

                Mail::to($recipient)->queue(new QuotePaymentLinkMail($order, $url, $transportCents));

                Notification::make()
                    ->success()
                    ->title('Lien de paiement envoyé')
                    ->body("E-mail envoyé à {$recipient}.")
                    ->send();
            });
    }

    private function resolveOrder(): ?Order
    {
        return $this->caller?->record ?? null;
    }
}
