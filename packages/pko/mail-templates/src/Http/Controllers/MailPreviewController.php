<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Http\Controllers;

use Illuminate\Http\Response;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\MailTemplates\Support\MailTemplateRegistry;
use Pko\MailTemplates\Support\TemplateResolver;

/**
 * Prévisualisation des e-mails, hors production uniquement (cf. provider).
 * Permet de relire les contenus dans un navigateur sans déclencher d'envoi.
 */
class MailPreviewController
{
    public function index(): Response
    {
        $rows = '';

        foreach (MailTemplateRegistry::all() as $key => $meta) {
            $resolved = TemplateResolver::resolve($key);
            $state = $resolved === null
                ? '<span style="color:#b45309;">désactivé / sans contenu</span>'
                : '<span style="color:#15803d;">actif</span>';

            $rows .= sprintf(
                '<tr><td style="padding:6px 14px 6px 0;"><a href="/_mail/%s">%s</a></td>'
                .'<td style="padding:6px 14px 6px 0;color:#555;">%s</td><td style="padding:6px 0;">%s</td></tr>',
                e($key),
                e($key),
                e($meta['label']),
                $state,
            );
        }

        return response(
            '<!doctype html><meta charset="utf-8"><title>Aperçu des e-mails</title>'
            .'<body style="font-family:system-ui,sans-serif;padding:32px;">'
            .'<h1 style="font-size:18px;">Aperçu des e-mails transactionnels</h1>'
            .'<table style="font-size:14px;border-collapse:collapse;">'.$rows.'</table></body>'
        );
    }

    public function show(string $key): mixed
    {
        abort_unless(MailTemplateRegistry::has($key), 404, "Clé d'e-mail inconnue : {$key}");

        $mail = new TemplatedMail($key, $this->sampleValues($key));

        abort_unless(
            $mail->shouldSend(),
            404,
            "L'e-mail « {$key} » est désactivé ou sans contenu — rien à prévisualiser."
        );

        return $mail;
    }

    /**
     * Valeurs de démonstration : chaque placeholder déclaré reçoit une valeur
     * lisible, pour que l'aperçu montre un mail complet plutôt que des trous.
     *
     * @return array<string, string>
     */
    private function sampleValues(string $key): array
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

        $values = [];
        foreach (MailTemplateRegistry::get($key)['placeholders'] as $placeholder) {
            $values[$placeholder] = $samples[$placeholder] ?? strtoupper($placeholder);
        }

        return $values;
    }
}
