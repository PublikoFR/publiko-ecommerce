<?php

declare(strict_types=1);

namespace Pko\Account\Support;

/**
 * Libellé FR d'un statut de commande Lunar, pour l'affichage uniquement.
 *
 * Source de vérité : config('lunar.orders.statuses.<slug>.label').
 * Le slug stocké (base, comparaisons, webhooks) ne change jamais.
 */
final class OrderStatusLabel
{
    public static function of(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        $label = config('lunar.orders.statuses.'.$status.'.label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        $key = 'account::statuses.'.$status;
        if (trans()->has($key)) {
            return (string) __($key);
        }

        return $status;
    }

    /**
     * Options slug => libellé, pour les Select Filament.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $statuses = config('lunar.orders.statuses', []);
        $out = [];

        foreach ($statuses as $slug => $data) {
            $slug = (string) $slug;
            $out[$slug] = is_array($data) && is_string($data['label'] ?? null) && $data['label'] !== ''
                ? $data['label']
                : $slug;
        }

        return $out;
    }
}
