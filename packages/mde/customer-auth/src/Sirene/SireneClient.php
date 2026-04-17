<?php

declare(strict_types=1);

namespace Mde\CustomerAuth\Sirene;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SireneClient
{
    public function __construct(
        private string $baseUrl,
        private string $consumerKey,
        private string $consumerSecret,
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
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $d = (int) $digits[$i];
            if ($i % 2 === 1) {
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

        if (! $this->enabled || $this->consumerKey === '' || $this->consumerSecret === '') {
            return new SireneResult(status: Status::Pending, siret: $normalized);
        }

        try {
            $token = $this->getToken();
            if ($token === null) {
                return new SireneResult(status: Status::Pending, siret: $normalized);
            }

            $response = Http::withToken($token)
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

    private function getToken(): ?string
    {
        $hours = (int) config('mde-customer-auth.sirene.cache_token_hours', 6);

        return Cache::remember('mde.sirene.token', now()->addHours($hours), function () {
            try {
                $response = Http::asForm()
                    ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->timeout($this->timeout)
                    ->post('https://api.insee.fr/token', ['grant_type' => 'client_credentials']);

                if (! $response->successful()) {
                    return null;
                }

                return (string) $response->json('access_token');
            } catch (\Throwable $e) {
                Log::warning('Sirene token fetch failed: '.$e->getMessage());

                return null;
            }
        });
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
