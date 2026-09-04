<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Pko\MailTemplates\Mail\TemplatedMail;

/**
 * Destinataire unique des e-mails internes (audience = équipe).
 *
 * L'adresse vit dans Storefront → Paramètres (`admin_email`). Les env
 * historiques restent un repli, pour ne pas couper les envois le temps
 * que le champ soit renseigné en back-office.
 */
final class AdminRecipient
{
    public static function email(): string
    {
        if (function_exists('admin_notification_email')) {
            return admin_notification_email();
        }

        foreach ([
            config('customer-auth.admin_notification_email'),
            config('loyalty.admin_email'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Envoie un mail équipe si un destinataire est configuré.
     *
     * Passe par OnceMailer : un observer de statut se rejoue, l'équipe
     * ne doit pas recevoir deux fois la même alerte.
     */
    public static function send(TemplatedMail $mail, Model $entity, ?string $scope = null): bool
    {
        $recipient = self::email();

        if ($recipient === '') {
            Log::info('Admin mail skipped: no recipient', [
                'mail_key' => $mail->key,
            ]);

            return false;
        }

        return OnceMailer::send($mail, $recipient, $entity, $scope);
    }
}
