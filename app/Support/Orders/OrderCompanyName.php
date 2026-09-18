<?php

declare(strict_types=1);

namespace App\Support\Orders;

use Lunar\Models\Order;

/**
 * Raison sociale affichée pour une commande (liste et fiche admin).
 *
 * L'adresse de facturation fait foi : c'est celle qui part sur la facture, et le
 * checkout la pré-remplit depuis le compte client. Le compte client ne sert que de
 * repli, pour les commandes dont l'adresse a été saisie sans raison sociale.
 */
final class OrderCompanyName
{
    public static function for(Order $order): ?string
    {
        foreach ([$order->billingAddress?->company_name, $order->customer?->company_name] as $name) {
            if (filled($name)) {
                return trim((string) $name);
            }
        }

        return null;
    }
}
