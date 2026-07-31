<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pipelines;

use Closure;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;

/**
 * Livraison en point relais : l'adresse de livraison de la commande devient celle
 * du point choisi.
 *
 * `CreateOrderAddresses` recopie l'adresse du panier — donc celle du client — alors
 * que le colis part au relais. La commande affichait ainsi une adresse de livraison
 * fausse en back-office, et l'adresse réelle n'existait que dans `meta.pickup_point`.
 *
 * L'adresse d'origine du client est conservée dans `meta.delivery_address_original`
 * (même convention que le module PrestaShop officiel) : elle reste nécessaire pour
 * identifier le destinataire final et pour un éventuel retour.
 *
 * Le nom, le téléphone et l'e-mail du client sont préservés — c'est ce qui permet au
 * point relais de remettre le colis à la bonne personne.
 */
class ApplyPickupPointAddress
{
    /**
     * @param  Closure(OrderContract): mixed  $next
     */
    public function handle(OrderContract $order, Closure $next): mixed
    {
        /** @var Order $order */
        $meta = $order->meta instanceof \ArrayObject
            ? $order->meta->getArrayCopy()
            : (array) ($order->meta ?? []);

        $point = $meta['pickup_point'] ?? null;

        if (! is_array($point) || blank($point['address1'] ?? null)) {
            return $next($order);
        }

        $address = $order->shippingAddress;

        if ($address === null) {
            return $next($order);
        }

        // Idempotent : une commande repassée dans le pipeline (mise à jour d'un
        // brouillon) ne doit pas écraser l'adresse client déjà sauvegardée.
        if (! isset($meta['delivery_address_original'])) {
            $meta['delivery_address_original'] = $address->only([
                'company_name', 'line_one', 'line_two', 'line_three',
                'city', 'state', 'postcode', 'country_id',
            ]);

            $order->forceFill(['meta' => $meta])->save();
        }

        $address->forceFill([
            'company_name' => (string) ($point['name'] ?? $address->company_name),
            'line_one' => (string) $point['address1'],
            'line_two' => null,
            'line_three' => null,
            'city' => (string) ($point['city'] ?? $address->city),
            'postcode' => (string) ($point['postcode'] ?? $address->postcode),
        ])->save();

        return $next($order->refresh());
    }
}
