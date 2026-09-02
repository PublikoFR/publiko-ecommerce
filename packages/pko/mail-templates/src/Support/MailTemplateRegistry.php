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
    /** Destinataire : le client final. */
    public const AUDIENCE_CUSTOMER = 'customer';

    /** Destinataire : l'équipe de la boutique. */
    public const AUDIENCE_ADMIN = 'admin';

    /** Envoyé aux deux, en deux exemplaires distincts. */
    public const AUDIENCE_BOTH = 'both';

    /**
     * @return array<string, array{group: string, audience: string, label: string, placeholders: array<int, string>, required: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            'account.welcome' => [
                'group' => 'account',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Bienvenue / inscription du compte',
                'placeholders' => ['first_name', 'company_name', 'verify_url'],
                'required' => ['verify_url'],
            ],
            'account.activated' => [
                'group' => 'account',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Activation du compte',
                'placeholders' => ['first_name', 'company_name', 'account_url'],
                'required' => [],
            ],
            'order.first_order_gift' => [
                'group' => 'order',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Première commande',
                'placeholders' => ['first_name', 'company_name', 'order_reference'],
                'required' => ['order_reference'],
            ],
            'order.confirmed' => [
                'group' => 'order',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Confirmation de commande',
                'placeholders' => ['first_name', 'order_reference', 'order_total', 'order_url'],
                'required' => ['order_reference'],
            ],
            'order.payment_received' => [
                'group' => 'order',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Paiement confirmé',
                'placeholders' => ['first_name', 'order_reference', 'order_total'],
                'required' => ['order_reference'],
            ],
            'order.shipped' => [
                'group' => 'order',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Commande expédiée',
                'placeholders' => ['first_name', 'order_reference', 'tracking_url', 'carrier_name'],
                'required' => ['order_reference', 'tracking_url'],
            ],
            'order.ready_for_pickup' => [
                'group' => 'order',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Commande disponible au retrait',
                'placeholders' => ['first_name', 'order_reference', 'pickup_name', 'pickup_address'],
                'required' => ['order_reference'],
            ],
            'order.shipped_by_supplier' => [
                'group' => 'order',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Commande expédiée par le partenaire',
                'placeholders' => ['first_name', 'order_reference', 'supplier_name', 'tracking_url'],
                'required' => ['order_reference'],
            ],
            'order.delayed' => [
                'group' => 'order',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Retard de commande',
                'placeholders' => ['first_name', 'order_reference', 'new_date'],
                'required' => ['order_reference', 'new_date'],
            ],
            'quote.reminder' => [
                'group' => 'commercial',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Relance devis',
                'placeholders' => ['first_name', 'quote_reference', 'quote_url'],
                'required' => ['quote_reference'],
            ],
            'cart.abandoned' => [
                'group' => 'commercial',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Panier non finalisé',
                'placeholders' => ['first_name', 'cart_url'],
                'required' => ['cart_url'],
            ],
            'order.delivered' => [
                'group' => 'order',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Commande livrée',
                'placeholders' => ['first_name', 'order_reference'],
                'required' => ['order_reference'],
            ],
            'order.review_request' => [
                'group' => 'commercial',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Demande d\'avis',
                'placeholders' => ['first_name', 'order_reference', 'review_url'],
                'required' => ['review_url'],
            ],
            'support.request_received' => [
                'group' => 'support',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Demande SAV reçue',
                'placeholders' => ['first_name', 'subject_label', 'order_reference'],
                'required' => [],
            ],
            'support.return_accepted' => [
                'group' => 'support',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Retour SAV accepté',
                'placeholders' => ['first_name', 'product_label', 'return_label_url'],
                'required' => ['return_label_url'],
            ],
            'credit_note.available' => [
                'group' => 'support',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Avoir disponible',
                'placeholders' => ['first_name', 'credit_note_reference', 'credit_note_total', 'account_url'],
                'required' => ['credit_note_reference'],
            ],
            'loyalty.tier_unlocked' => [
                'group' => 'loyalty',
                'audience' => self::AUDIENCE_CUSTOMER,
                'label' => 'Nouveau palier de fidélité',
                'placeholders' => ['first_name', 'tier_name', 'tier_benefit', 'total_points'],
                'required' => ['tier_name'],
            ],
            'loyalty.anniversary' => [
                'group' => 'loyalty',
                'audience' => self::AUDIENCE_CUSTOMER,
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

    /** Destinataire d'un e-mail, avec repli sur le client si la clé est inconnue. */
    public static function audience(string $key): string
    {
        return self::all()[$key]['audience'] ?? self::AUDIENCE_CUSTOMER;
    }

    /** @return array{group: string, audience: string, label: string, placeholders: array<int, string>, required: array<int, string>} */
    public static function get(string $key): array
    {
        $entry = self::all()[$key] ?? null;

        if ($entry === null) {
            throw new \InvalidArgumentException("Clé d'e-mail inconnue : {$key}");
        }

        return $entry;
    }
}
