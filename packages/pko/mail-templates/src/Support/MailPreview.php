<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

use Illuminate\Support\Facades\Mail;
use Pko\MailTemplates\Mail\TemplatedMail;
use Throwable;

/**
 * Rendu d'un e-mail avec des valeurs de démonstration.
 *
 * Sert à la fois la route de preview locale et le panneau d'aperçu du
 * back-office. Le rendu passe par `TemplatedMail`, donc par le chemin réel
 * d'envoi : l'aperçu montre ce que le client recevra, pas une approximation.
 */
final class MailPreview
{
    /**
     * Valeurs lisibles pour chaque placeholder connu. Un aperçu doit montrer un
     * message complet — des trous à la place des variables ne permettent pas de
     * juger le texte.
     *
     * @return array<string, string>
     */
    public static function sampleValues(string $key): array
    {
        $samples = [
            'first_name' => 'Camille',
            'company_name' => 'Fermetures du Sud',
            'order_reference' => 'CMD-10432',
            'order_total' => '1 248,90',
            'quote_reference' => 'DEV-2098',
            'tier_name' => 'Argent',
            'tier_benefit' => 'la remise fidélité de 3 %',
            'total_points' => '4 250',
            'duration_label' => '2 ans',
            'new_date' => '18 septembre 2026',
            'carrier_name' => 'Chronopost',
            'supplier_name' => 'Somfy',
            'pickup_name' => 'Relais Presse du Centre',
            'pickup_address' => '12 rue des Lilas, 34000 Montpellier',
            'product_label' => 'Motorisation portail battant',
            'subject_label' => 'Produit non conforme',
            'credit_note_reference' => 'AV-2026-041',
            'credit_note_total' => '312,00',
            'verify_url' => url('/verification-email/apercu'),
            'account_url' => url('/compte'),
            'order_url' => url('/compte/commandes/CMD-10432'),
            'cart_url' => url('/panier'),
            'quote_url' => url('/compte/devis/DEV-2098'),
            'tracking_url' => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=XX000000000FR',
            'review_url' => url('/avis'),
            'return_label_url' => url('/sav/etiquette/apercu'),
        ];

        if (! MailTemplateRegistry::has($key)) {
            return [];
        }

        $values = [];
        foreach (MailTemplateRegistry::get($key)['placeholders'] as $placeholder) {
            $values[$placeholder] = $samples[$placeholder] ?? strtoupper($placeholder);
        }

        return $values;
    }

    public static function mail(string $key): TemplatedMail
    {
        return new TemplatedMail($key, self::sampleValues($key));
    }

    /**
     * Envoie le modèle, rempli de valeurs de démonstration, à une adresse
     * choisie — pour juger le rendu dans un vrai client mail.
     *
     * Envoi direct plutôt que via OnceMailer : la garde anti-doublon
     * n'autoriserait qu'un seul test par modèle et par destinataire.
     */
    public static function sendTest(string $key, string $recipient): void
    {
        $mail = self::mail($key);

        if (! $mail->shouldSend()) {
            throw new \RuntimeException(__('pko-mail-templates::admin.test.disabled'));
        }

        // Sans ce préfixe, un e-mail de test est indiscernable d'un vrai message
        // dans la boîte de réception.
        $mail->subjectPrefix = '[TEST] ';

        Mail::to($recipient)->send($mail);
    }

    /** Adresse proposée par défaut pour un envoi de test. */
    public static function defaultTestRecipient(): string
    {
        // L'utilisateur connecté d'abord : c'est lui qui veut voir le rendu.
        $staffEmail = filament()->auth()->user()?->email;

        if (is_string($staffEmail) && $staffEmail !== '') {
            return $staffEmail;
        }

        if (function_exists('brand_setting')) {
            $configured = brand_setting('admin_email');

            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        return (string) config('mail.from.address', '');
    }

    /**
     * HTML complet du message, ou un message d'explication si le modèle est
     * désactivé ou son contenu invalide — l'aperçu ne doit jamais planter la
     * page qui l'affiche.
     */
    public static function render(string $key): string
    {
        $mail = self::mail($key);

        if (! $mail->shouldSend()) {
            return '<p style="padding:1rem;font-family:system-ui,sans-serif;color:#92400e;">'
                .e(__('pko-mail-templates::admin.preview.disabled'))
                .'</p>';
        }

        try {
            return $mail->render();
        } catch (Throwable $e) {
            return '<p style="padding:1rem;font-family:system-ui,sans-serif;color:#b91c1c;">'
                .e(__('pko-mail-templates::admin.preview.error', ['message' => $e->getMessage()]))
                .'</p>';
        }
    }
}
