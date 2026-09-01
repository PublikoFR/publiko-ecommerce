<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Support;

/**
 * Catalogue des e-mails transactionnels : clés, placeholders disponibles et
 * placeholders obligatoires.
 *
 * Ce fichier est du CODE : il ne contient aucun texte commercial ni nom de
 * marque (cf. §3.0 du CLAUDE.md). Les contenus vivent dans
 * `database/content/<locale>.php` (data) puis en base (éditable).
 *
 * `required` liste les placeholders sans lesquels le mail perd sa fonction —
 * un lien de suivi, une référence de commande. L'éditeur back-office refuse
 * d'enregistrer un contenu qui les a perdus.
 */
final class MailTemplateRegistry
{
    /**
     * @return array<string, array{group: string, label: string, placeholders: array<int, string>, required: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            'account.welcome' => [
                'group' => 'account',
                'label' => 'Bienvenue / inscription du compte',
                'placeholders' => ['first_name', 'company_name', 'verify_url'],
                'required' => ['verify_url'],
            ],
            'account.activated' => [
                'group' => 'account',
                'label' => 'Activation du compte',
                'placeholders' => ['first_name', 'company_name', 'account_url'],
                'required' => [],
            ],
            'order.first_order_gift' => [
                'group' => 'order',
                'label' => 'Première commande',
                'placeholders' => ['first_name', 'company_name', 'order_reference'],
                'required' => ['order_reference'],
            ],
            'order.confirmed' => [
                'group' => 'order',
                'label' => 'Confirmation de commande',
                'placeholders' => ['first_name', 'order_reference', 'order_total', 'order_url'],
                'required' => ['order_reference'],
            ],
            'order.payment_received' => [
                'group' => 'order',
                'label' => 'Paiement confirmé',
                'placeholders' => ['first_name', 'order_reference', 'order_total'],
                'required' => ['order_reference'],
            ],
            'order.shipped' => [
                'group' => 'order',
                'label' => 'Commande expédiée',
                'placeholders' => ['first_name', 'order_reference', 'tracking_url', 'carrier_name'],
                'required' => ['order_reference', 'tracking_url'],
            ],
            'order.ready_for_pickup' => [
                'group' => 'order',
                'label' => 'Commande disponible au retrait',
                'placeholders' => ['first_name', 'order_reference', 'pickup_name', 'pickup_address'],
                'required' => ['order_reference'],
            ],
            'order.shipped_by_supplier' => [
                'group' => 'order',
                'label' => 'Commande expédiée par le partenaire',
                'placeholders' => ['first_name', 'order_reference', 'supplier_name', 'tracking_url'],
                'required' => ['order_reference'],
            ],
            'order.delayed' => [
                'group' => 'order',
                'label' => 'Retard de commande',
                'placeholders' => ['first_name', 'order_reference', 'new_date'],
                'required' => ['order_reference', 'new_date'],
            ],
            'quote.reminder' => [
                'group' => 'commercial',
                'label' => 'Relance devis',
                'placeholders' => ['first_name', 'quote_reference', 'quote_url'],
                'required' => ['quote_reference'],
            ],
            'cart.abandoned' => [
                'group' => 'commercial',
                'label' => 'Panier non finalisé',
                'placeholders' => ['first_name', 'cart_url'],
                'required' => ['cart_url'],
            ],
            'order.delivered' => [
                'group' => 'order',
                'label' => 'Commande livrée',
                'placeholders' => ['first_name', 'order_reference'],
                'required' => ['order_reference'],
            ],
            'order.review_request' => [
                'group' => 'commercial',
                'label' => 'Demande d\'avis',
                'placeholders' => ['first_name', 'order_reference', 'review_url'],
                'required' => ['review_url'],
            ],
            'support.request_received' => [
                'group' => 'support',
                'label' => 'Demande SAV reçue',
                'placeholders' => ['first_name', 'subject_label', 'order_reference'],
                'required' => [],
            ],
            'support.return_accepted' => [
                'group' => 'support',
                'label' => 'Retour SAV accepté',
                'placeholders' => ['first_name', 'product_label', 'return_label_url'],
                'required' => ['return_label_url'],
            ],
            'credit_note.available' => [
                'group' => 'support',
                'label' => 'Avoir disponible',
                'placeholders' => ['first_name', 'credit_note_reference', 'credit_note_total', 'account_url'],
                'required' => ['credit_note_reference'],
            ],
            'loyalty.tier_unlocked' => [
                'group' => 'loyalty',
                'label' => 'Nouveau palier de fidélité',
                'placeholders' => ['first_name', 'tier_name', 'tier_benefit', 'total_points'],
                'required' => ['tier_name'],
            ],
            'loyalty.anniversary' => [
                'group' => 'loyalty',
                'label' => 'Anniversaire de fidélité',
                'placeholders' => ['first_name', 'duration_label'],
                'required' => ['duration_label'],
            ],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** @return array{group: string, label: string, placeholders: array<int, string>, required: array<int, string>} */
    public static function get(string $key): array
    {
        $entry = self::all()[$key] ?? null;

        if ($entry === null) {
            throw new \InvalidArgumentException("Clé d'e-mail inconnue : {$key}");
        }

        return $entry;
    }
}
