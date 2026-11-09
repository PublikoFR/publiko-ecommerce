<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Observers;

use Illuminate\Support\Facades\Mail;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Mail\AccountActivatedMail;

/**
 * Envoie l'e-mail « votre compte a été validé » au passage `pending` → `active`.
 *
 * Observer plutôt qu'envoi dans l'action : l'équipe valide un compte soit par
 * l'action « Valider le compte », soit en changeant le Select « Statut compte » de
 * la fiche. Les deux chemins doivent notifier le client, une seule fois par
 * validation. Seule la transition depuis `pending` compte : réactiver un compte
 * suspendu (`banned` → `active`) n'est pas une validation et ne renvoie rien.
 */
class CustomerActivationObserver
{
    public function updated(Customer $customer): void
    {
        if (! $customer->wasChanged('pko_status')
            || $customer->getOriginal('pko_status') !== 'pending'
            || $customer->getAttribute('pko_status') !== 'active') {
            return;
        }

        $email = $customer->users()->first()?->email;

        if (blank($email)) {
            return;
        }

        // Un échec SMTP ne doit pas faire échouer l'enregistrement de la fiche :
        // le compte est validé, seul le mail manque (visible dans les logs).
        try {
            $mail = new AccountActivatedMail($customer);

            if ($mail->shouldSend()) {
                Mail::to($email)->send($mail);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
