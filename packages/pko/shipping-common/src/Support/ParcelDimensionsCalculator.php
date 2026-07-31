<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Support;

use Lunar\Models\Order;

/**
 * Dimensions du colis (cm) transmises au transporteur.
 *
 * Chronopost refuse une LT sans dimensions plausibles sur certains produits, et le
 * calcul du poids volumétrique en dépend. À défaut de gestion de cartons (hors
 * périmètre V1), on prend l'encombrement maximal des variantes de la commande,
 * avec repli sur les dimensions du carton par défaut du transporteur.
 */
final class ParcelDimensionsCalculator
{
    /**
     * @return array{length: float, width: float, height: float}
     */
    public static function fromOrder(Order $order, string $carrierCode): array
    {
        $defaults = self::defaults($carrierCode);

        $max = ['length' => 0.0, 'width' => 0.0, 'height' => 0.0];

        foreach ($order->lines as $line) {
            $variant = $line->purchasable;
            if ($variant === null) {
                continue;
            }

            foreach ($max as $axis => $current) {
                $max[$axis] = max($current, self::toCentimetres(
                    (float) ($variant->{$axis.'_value'} ?? 0),
                    (string) ($variant->{$axis.'_unit'} ?? 'cm'),
                ));
            }
        }

        // Une seule dimension renseignée ne suffit pas à décrire un colis : tant que
        // les trois ne sont pas connues, on garde le carton par défaut pour toutes.
        if ($max['length'] <= 0.0 || $max['width'] <= 0.0 || $max['height'] <= 0.0) {
            return $defaults;
        }

        return [
            'length' => round($max['length'], 1),
            'width' => round($max['width'], 1),
            'height' => round($max['height'], 1),
        ];
    }

    /**
     * @return array{length: float, width: float, height: float}
     */
    public static function defaults(string $carrierCode): array
    {
        $configured = config("{$carrierCode}.packaging.default_dimensions_cm", []);

        return [
            'length' => (float) ($configured['length'] ?? 30),
            'width' => (float) ($configured['width'] ?? 20),
            'height' => (float) ($configured['height'] ?? 15),
        ];
    }

    private static function toCentimetres(float $value, string $unit): float
    {
        return match (strtolower($unit)) {
            'cm' => $value,
            'mm' => $value / 10,
            'm' => $value * 100,
            'in' => $value * 2.54,
            default => $value,
        };
    }
}
