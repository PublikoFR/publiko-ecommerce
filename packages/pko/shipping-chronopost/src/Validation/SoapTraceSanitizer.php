<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Validation;

use DOMDocument;

/**
 * Prépare une trace SOAP pour être jointe à un mail : XML indenté, mot de passe
 * masqué, blocs base64 (étiquettes) tronqués.
 */
final class SoapTraceSanitizer
{
    public const PASSWORD_MASK = '********';

    /** En deçà, une valeur n'est pas considérée comme un blob base64. */
    private const BASE64_MIN_LENGTH = 200;

    private const BASE64_KEEP = 80;

    public static function sanitize(string $xml): string
    {
        $xml = self::pretty($xml);

        // <password>, <ns1:password>, <password xsi:type="…"> : valeur masquée quel
        // que soit le préfixe. Appliqué après indentation, qui ne recrée rien.
        $xml = (string) preg_replace(
            '#(<((?:[\w.-]+:)?password)\b[^>]*>)(.*?)(</\2>)#is',
            '$1'.self::PASSWORD_MASK.'$4',
            $xml,
        );

        return (string) preg_replace_callback(
            '#>([A-Za-z0-9+/=\r\n]{'.self::BASE64_MIN_LENGTH.',})<#',
            static function (array $m): string {
                $blob = (string) preg_replace('/\s+/', '', $m[1]);

                if (strlen($blob) < self::BASE64_MIN_LENGTH) {
                    return $m[0];
                }

                return sprintf('>%s…[base64 tronqué, %d caractères]<', substr($blob, 0, self::BASE64_KEEP), strlen($blob));
            },
            $xml,
        );
    }

    private static function pretty(string $xml): string
    {
        if (trim($xml) === '') {
            return $xml;
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? (string) $dom->saveXML() : $xml;
    }
}
