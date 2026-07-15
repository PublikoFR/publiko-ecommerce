<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Sirene;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SireneClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private bool $enabled,
        private int $timeout = 5,
    ) {}

    /**
     * Normalize + validate SIRET (14 digits, Luhn).
     */
    public static function validateSiret(string $siret): bool
    {
        $digits = preg_replace('/\D/', '', $siret) ?? '';
        if (strlen($digits) !== 14) {
            return false;
        }
        // Luhn : sur un SIRET (14 chiffres, longueur paire), on double un chiffre
        // sur deux en partant de la droite → ce sont les index PAIRS depuis la
        // gauche (0, 2, …, 12). Doubler les index impairs (ancien bug) rejetait
        // tous les SIRET valides.
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $d = (int) $digits[$i];
            if ($i % 2 === 0) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }

        return $sum % 10 === 0;
    }

    public function verify(string $siret): SireneResult
    {
        $normalized = preg_replace('/\D/', '', $siret) ?? '';

        if (! $this->enabled || $this->apiKey === '') {
            return new SireneResult(status: Status::Pending, siret: $normalized);
        }

        try {
            $header = (string) config('customer-auth.sirene.api_key_header', 'X-INSEE-Api-Key-Integration');

            $response = Http::withHeaders([$header => $this->apiKey])
                ->timeout($this->timeout)
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/').'/siret/'.$normalized);

            if ($response->status() === 404) {
                return new SireneResult(status: Status::Inactive, siret: $normalized);
            }

            if (! $response->successful()) {
                Log::warning('Sirene API non-2xx', ['status' => $response->status(), 'body' => $response->body()]);

                return new SireneResult(status: Status::Pending, siret: $normalized);
            }

            return $this->parseResponse($normalized, $response->json('etablissement', []));
        } catch (\Throwable $e) {
            Log::warning('Sirene API error: '.$e->getMessage());

            return new SireneResult(status: Status::Pending, siret: $normalized);
        }
    }

    private function parseResponse(string $siret, array $etablissement): SireneResult
    {
        if (empty($etablissement)) {
            return new SireneResult(status: Status::Inactive, siret: $siret);
        }

        $isActive = ($etablissement['etatAdministratifEtablissement'] ?? null) === 'A';
        if (! $isActive) {
            return new SireneResult(status: Status::Inactive, siret: $siret);
        }

        $unite = $etablissement['uniteLegale'] ?? [];
        $adr = $etablissement['adresseEtablissement'] ?? [];

        $raison = $unite['denominationUniteLegale']
            ?? trim(($unite['prenom1UniteLegale'] ?? '').' '.($unite['nomUniteLegale'] ?? ''));

        $addressLine = trim(implode(' ', array_filter([
            $adr['numeroVoieEtablissement'] ?? null,
            $adr['typeVoieEtablissement'] ?? null,
            $adr['libelleVoieEtablissement'] ?? null,
        ])));

        return new SireneResult(
            status: Status::Active,
            siret: $siret,
            raisonSociale: $raison !== '' ? $raison : null,
            nafCode: $etablissement['activitePrincipaleEtablissement'] ?? ($unite['activitePrincipaleUniteLegale'] ?? null),
            nafLabel: null,
            addressLine1: $addressLine !== '' ? $addressLine : null,
            postcode: $adr['codePostalEtablissement'] ?? null,
            city: $adr['libelleCommuneEtablissement'] ?? null,
            category: $unite['categorieEntreprise'] ?? null,
        );
    }
}
