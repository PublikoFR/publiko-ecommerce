<?php

declare(strict_types=1);

namespace Pko\OrderNotifications\Support;

use Lunar\Models\Order;
use Pko\MailTemplates\Support\PhoneLink;

/**
 * Extraction des données d'une commande vers les placeholders des e-mails.
 */
final class OrderMailData
{
    /** Adresse de contact du client, dans l'ordre de fiabilité décroissante. */
    public static function recipient(Order $order): ?string
    {
        $email = $order->shippingAddress?->contact_email
            ?? $order->billingAddress?->contact_email
            ?? $order->customer?->users()->first()?->email;

        return is_string($email) && $email !== '' ? $email : null;
    }

    public static function firstName(Order $order): string
    {
        return (string) (
            $order->shippingAddress?->first_name
            ?? $order->billingAddress?->first_name
            ?? $order->customer?->first_name
            ?? ''
        );
    }

    public static function companyName(Order $order): string
    {
        return (string) (
            $order->billingAddress?->company_name
            ?? $order->shippingAddress?->company_name
            ?? $order->customer?->company_name
            ?? ''
        );
    }

    /**
     * Montant HT formaté à la française.
     *
     * Les textes client annoncent explicitement « € HT » : c'est donc `sub_total`
     * (hors taxes) qu'il faut afficher, jamais `total` (TTC). Afficher un TTC
     * sous un libellé HT donnerait au client un montant faux.
     */
    public static function totalExcludingTax(Order $order): string
    {
        $cents = (int) ($order->sub_total?->value ?? 0);

        return number_format($cents / 100, 2, ',', ' ');
    }

    /** @return array<string, string> */
    public static function base(Order $order): array
    {
        return [
            'first_name' => self::firstName($order),
            'company_name' => self::companyName($order),
            'order_reference' => (string) $order->reference,
            'order_total' => self::totalExcludingTax($order),
            'order_url' => url('/compte/commandes/'.$order->reference),
        ];
    }

    public static function contactName(Order $order): string
    {
        $last = (string) (
            $order->shippingAddress?->last_name
            ?? $order->billingAddress?->last_name
            ?? $order->customer?->last_name
            ?? ''
        );

        return trim(self::firstName($order).' '.$last);
    }

    public static function phone(Order $order): string
    {
        return (string) (
            $order->shippingAddress?->contact_phone
            ?? $order->billingAddress?->contact_phone
            ?? ''
        );
    }

    public static function statusLabel(Order $order): string
    {
        $label = config('lunar.orders.statuses.'.$order->status.'.label');

        return is_string($label) && $label !== '' ? $label : (string) $order->status;
    }

    /** @return array<string, string> */
    public static function admin(Order $order): array
    {
        $phone = self::phone($order);

        return [
            'company_name' => self::companyName($order),
            'contact_name' => self::contactName($order),
            'email' => self::recipient($order) ?? '',
            'phone' => $phone,
            'phone_url' => PhoneLink::href($phone),
            'order_reference' => (string) $order->reference,
            'order_total' => self::totalExcludingTax($order),
            'order_status' => self::statusLabel($order),
            'admin_url' => url('/admin/orders/'.$order->id),
        ];
    }
}
