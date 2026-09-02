<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Pko\MailTemplates\Mail\TemplatedMail;
use Throwable;

/**
 * Envoi d'un e-mail transactionnel au plus une fois par (modèle, entité,
 * destinataire).
 *
 * Les déclencheurs disponibles sont des observers de changement de statut :
 * ils peuvent se rejouer (sauvegarde répétée, reprise de webhook, backfill).
 * Sans garde, un client reçoit deux fois « paiement reçu ». La réservation est
 * posée AVANT l'envoi : en cas de course entre deux processus, seul celui qui
 * gagne l'insertion unique envoie.
 */
final class OnceMailer
{
    /**
     * @param  string|null  $scope  Distingue plusieurs envois légitimes du même
     *                              modèle à la même entité. Sans lui, un e-mail
     *                              récurrent (anniversaire) ne partirait qu'une
     *                              seule fois dans la vie du client.
     * @return bool true si l'e-mail a effectivement été envoyé.
     */
    public static function send(TemplatedMail $mail, string $recipient, Model $entity, ?string $scope = null): bool
    {
        if (! $mail->shouldSend() || $recipient === '') {
            return false;
        }

        $key = $scope === null ? $mail->key : $mail->key.'#'.$scope;

        if (! self::reserve($key, $entity, $recipient)) {
            return false;
        }

        try {
            Mail::to($recipient)->send($mail);

            return true;
        } catch (Throwable $e) {
            // L'envoi a échoué : on libère la réservation pour qu'un rejeu
            // ultérieur puisse retenter, plutôt que de perdre le mail.
            self::release($key, $entity, $recipient);

            Log::warning('Templated mail failed', [
                'mail_key' => $mail->key,
                'entity' => $entity::class.'#'.$entity->getKey(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function reserve(string $key, Model $entity, string $recipient): bool
    {
        if (! Schema::hasTable('pko_mail_dispatch_log')) {
            // Table absente (migration pas encore jouée) : on n'empêche pas
            // l'envoi, on perd seulement la protection contre les doublons.
            return true;
        }

        // insertOrIgnore + contrainte unique : l'unicité est arbitrée par la base,
        // pas par un select-puis-insert qui laisserait une fenêtre de course.
        return DB::table('pko_mail_dispatch_log')->insertOrIgnore([
            'mail_key' => $key,
            'entity_type' => $entity::class,
            'entity_id' => $entity->getKey(),
            'recipient' => $recipient,
            'sent_at' => now(),
        ]) > 0;
    }

    private static function release(string $key, Model $entity, string $recipient): void
    {
        if (! Schema::hasTable('pko_mail_dispatch_log')) {
            return;
        }

        DB::table('pko_mail_dispatch_log')
            ->where('mail_key', $key)
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->where('recipient', $recipient)
            ->delete();
    }
}
