<?php

declare(strict_types=1);

namespace App\Support\Orders;

use Lunar\Models\Order;

/**
 * Raison sociale affichée pour une commande (liste et fiche admin).
 *
 * Source unique : le compte client (`lunar_customers.company_name`), renseigné à
 * l'inscription depuis la vérification SIRET. La raison sociale saisie sur une
 * adresse est libre et non vérifiée : elle n'est jamais lue ici. Une commande sans
 * compte client n'a donc pas de raison sociale.
 */
final class OrderCompanyName
{
    public static function for(Order $order): ?string
    {
        $name = trim((string) $order->customer?->company_name);

        return $name !== '' ? $name : null;
    }
}
