<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Validation;

/**
 * Journal des échanges SOAP bruts (requête / réponse XML) capturés pendant le kit
 * de validation Chronopost.
 */
final class SoapExchangeLog
{
    /** @var list<array{method: string, request: string, response: string}> */
    private array $exchanges = [];

    public function record(string $request, string $response): void
    {
        $this->exchanges[] = [
            'method' => self::methodOf($request),
            'request' => $request,
            'response' => $response,
        ];
    }

    /**
     * Renvoie les échanges enregistrés depuis le dernier appel, puis vide le journal.
     *
     * @return list<array{method: string, request: string, response: string}>
     */
    public function take(): array
    {
        $exchanges = $this->exchanges;
        $this->exchanges = [];

        return $exchanges;
    }

    /**
     * Nom de l'opération = premier élément du `<Body>` de l'enveloppe.
     */
    public static function methodOf(string $request): string
    {
        return preg_match('#<(?:[\w.-]+:)?Body[^>]*>\s*<(?:[\w.-]+:)?([\w.-]+)#', $request, $m) === 1
            ? $m[1]
            : 'soap';
    }
}
