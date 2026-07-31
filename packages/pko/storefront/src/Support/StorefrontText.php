<?php

declare(strict_types=1);

namespace Pko\Storefront\Support;

use Pko\ShippingCommon\Settings\ShippingSettings;
use Throwable;

/**
 * Interpolation des variables dynamiques dans les textes éditoriaux du front
 * (bandeaux du header, USPs, textes libres saisis en back-office).
 *
 * Problème résolu : le seuil de franco de port était recopié en dur dans chaque
 * bandeau (« Livraison offerte dès 125 € HT »). Modifier le franco dans
 * Back-office → Transporteurs ne se répercutait donc nulle part sur le front.
 * Les textes acceptent désormais des variables `{{port_franco}}` résolues au
 * rendu depuis la source de vérité unique [[ShippingSettings]].
 *
 * Syntaxe : `{{nom_variable}}`, espaces internes tolérés (`{{ port_franco }}`),
 * insensible à la casse. Une variable inconnue est laissée telle quelle (on
 * n'efface jamais silencieusement ce que l'utilisateur a saisi).
 *
 * Les `{{ }}` d'un Setting stocké en base ne sont jamais évalués par Blade
 * (la valeur est échappée à l'affichage), il n'y a donc pas de risque
 * d'injection de template ici.
 */
final class StorefrontText
{
    /**
     * Variables disponibles, pour l'aide contextuelle du back-office.
     *
     * @return array<string, string> nom de variable => description
     */
    public static function availableVariables(): array
    {
        return [
            'port_franco' => 'Seuil de livraison offerte, suffixe HT inclus (ex. « 500 € HT »)',
            'port_franco_montant' => 'Seuil de livraison offerte, montant seul (ex. « 500 € »)',
        ];
    }

    /**
     * Remplace les variables `{{...}}` d'un texte éditorial.
     */
    public static function render(?string $text): string
    {
        $text = (string) $text;

        if ($text === '' || ! str_contains($text, '{{')) {
            return $text;
        }

        $values = self::values();

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static fn (array $m): string => $values[strtolower($m[1])] ?? $m[0],
            $text,
        );
    }

    /**
     * Valeurs résolues des variables.
     *
     * @return array<string, string>
     */
    private static function values(): array
    {
        $amount = self::francoAmount();

        return [
            'port_franco' => $amount.' HT',
            'port_franco_montant' => $amount,
        ];
    }

    /**
     * Seuil de franco formaté (« 500 € »). Le montant est stocké en cents HT.
     */
    private static function francoAmount(): string
    {
        try {
            $cents = ShippingSettings::thresholdCents();
        } catch (Throwable) {
            $cents = 0;
        }

        $euros = $cents / 100;
        $decimals = fmod($euros, 1.0) === 0.0 ? 0 : 2;

        return number_format($euros, $decimals, ',', ' ').' €';
    }
}
