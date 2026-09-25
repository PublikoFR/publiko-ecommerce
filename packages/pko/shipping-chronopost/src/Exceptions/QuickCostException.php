<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Exceptions;

use RuntimeException;

class QuickCostException extends RuntimeException
{
    /**
     * Codes d'erreur du service quickCost (doc Web Services VL3.25.10.10, §4.3.6).
     */
    public const ERROR_MESSAGES = [
        1 => 'erreur système Chronopost',
        2 => 'paramètre obligatoire manquant',
        3 => 'mot de passe ne correspondant pas au numéro de contrat',
        4 => 'code produit incohérent avec les codes de départ et d\'arrivée',
        5 => 'aucun tarif trouvé pour ces données',
    ];

    public static function missingCredentials(): self
    {
        return new self('Chronopost credentials missing for quickCost call.');
    }

    public static function soapFailure(string $message, ?\Throwable $previous = null): self
    {
        return new self('Chronopost quickCost SOAP failure: '.$message, 0, $previous);
    }

    public static function apiError(int $code, string $apiMessage): self
    {
        $label = self::ERROR_MESSAGES[$code] ?? 'erreur inconnue';
        $detail = $apiMessage !== '' ? " (API : {$apiMessage})" : '';

        return new self("Chronopost quickCost returned error [{$code}]: {$label}{$detail}", $code);
    }

    public static function amountNotFound(string $productCode): self
    {
        return new self(
            "Chronopost quickCost returned no usable amount for product {$productCode} "
            .'(montant nul ou absent — compte sans tarif pour ce produit ?).',
            5,
        );
    }
}
