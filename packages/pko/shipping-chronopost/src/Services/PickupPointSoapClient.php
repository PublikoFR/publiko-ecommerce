<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Services;

use Illuminate\Support\Carbon;
use Pko\ShippingChronopost\Exceptions\PickupPointException;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Pure SOAP client for Chronopost PointRelaisServiceWS.
 *
 * Official WSDL: https://ws.chronopost.fr/recherchebt-wsdl/PointRelaisServiceWS?wsdl
 * Method: recherchePointChronopostInter
 */
class PickupPointSoapClient
{
    /**
     * Endpoint CXF. L'ancienne valeur `recherchebt-wsdl/…` répond **404** : toute
     * recherche de point relais échouait à la construction du SoapClient, donc
     * avant même d'utiliser les identifiants. Vérifié le 2026-07-31 — les quatre
     * variantes de casse/chemin testées, seule celle-ci renvoie 200.
     */
    public const DEFAULT_WSDL = 'https://ws.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl';

    /**
     * Type de point recherché. `P` = point relais Pickup.
     *
     * Le WS refuse un `type` vide (erreur 300 « Il faut que le type ou le pudoType
     * soient renseignés »). `A` (agence / bureau de poste) répond explicitement
     * « pour l'instant non supporté ».
     */
    private const POINT_TYPE = 'P';

    /**
     * Service demandé. Vide → erreur 300 « service [] incorrect ». `L` et `T`
     * renvoient le même jeu de points ; on retient `L` (livraison).
     */
    private const SERVICE_CODE = 'L';

    /**
     * Produit Chronopost lié à la recherche : `86` = Chrono Relais 13H (doc WS
     * VL3.25.10.10 §2.4.2.a — champ obligatoire). Vérifié sur le
     * compte test le 2026-09-25 : même jeu de points qu'avec l'ancienne valeur vide.
     */
    private const PRODUCT_CODE_RELAIS = '86';

    /**
     * Champ `weight` : grammes, 5 chiffres au plus (`[0-9](0,5)`).
     */
    private const MAX_WEIGHT_GRAMS = 99999;

    /**
     * Abréviations des jours, indexées sur le `jour` du WS (1 = lundi … 7 = dimanche).
     */
    private const DAY_LABELS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];

    /**
     * @param  array{account?: string, password?: string}  $credentials
     */
    public function __construct(
        protected array $credentials,
        protected ?string $wsdl = null,
        protected ?SoapClient $client = null,
        protected int $timeoutSeconds = 8,
    ) {}

    /**
     * @return array<int, array{
     *     id: string, name: string, address1: string, postcode: string, city: string,
     *     country_code: string, distance_km: float|null,
     *     latitude: float|null, longitude: float|null,
     *     opening_hours: string|null,
     *     opening_schedule: list<array{day: int, label: string, hours: string}>|null,
     *     max_weight_kg: float|null
     * }>
     *
     * @throws PickupPointException
     */
    public function search(
        string $postcode,
        string $countryCode = 'FR',
        ?string $serviceCode = null,
        ?string $city = null,
        ?int $weightGrams = null,
    ): array {
        $account = (string) ($this->credentials['account'] ?? '');
        $password = (string) ($this->credentials['password'] ?? '');

        if ($account === '' || $password === '') {
            throw new PickupPointException('Chronopost pickup: missing account credentials');
        }

        try {
            $client = $this->client ?? $this->buildSoapClient();

            $response = $client->recherchePointChronopostInter([
                'accountNumber' => $account,
                'password' => $password,
                'address' => '',
                'zipCode' => $postcode,
                // `city` est obligatoire (erreur 700 si vide) et **prime sur le code
                // postal** quand les deux divergent : zipCode=75001 + city=Béziers
                // renvoie les points de Béziers. Passer une ville périmée après un
                // changement de code postal donnerait donc des résultats à côté.
                // Le code postal lui-même est une valeur de remplissage acceptée par
                // le WS, qui géolocalise alors sur le code postal — vérifié sur
                // 34500 / 75001 / 69003 / 33000 / 59000 / 06000.
                'city' => ($city !== null && trim($city) !== '') ? $city : $postcode,
                'countryCode' => $countryCode,
                'type' => self::POINT_TYPE,
                'productCode' => $serviceCode ?? self::PRODUCT_CODE_RELAIS,
                'service' => self::SERVICE_CODE,
                // Transmis quand il est connu, mais le WS ne filtre PAS dessus : un
                // poids de 25 kg renvoie les mêmes points (tous à poidsMaxi = 20).
                // Le filtre effectif est fait par ChronopostPickupPointProvider.
                'weight' => ($weightGrams !== null && $weightGrams > 0 && $weightGrams <= self::MAX_WEIGHT_GRAMS)
                    ? $weightGrams
                    : '',
                // Obligatoire selon la doc (JJ/MM/AAAA). Date du jour : le colis
                // part au plus tôt aujourd'hui.
                'shippingDate' => Carbon::now()->format('d/m/Y'),
                'maxPointChronopost' => 20,
                'maxDistanceSearch' => 20,
                'holidayTolerant' => 1,
                'language' => 'FR',
                'version' => '2.0',
            ]);
        } catch (SoapFault $e) {
            throw new PickupPointException(
                "Chronopost pickup SOAP fault: {$e->getMessage()}",
                previous: $e
            );
        } catch (Throwable $e) {
            throw new PickupPointException(
                "Chronopost pickup error: {$e->getMessage()}",
                previous: $e
            );
        }

        return $this->parseResponse($response);
    }

    protected function buildSoapClient(): SoapClient
    {
        return new SoapClient($this->wsdl ?? self::DEFAULT_WSDL, [
            'trace' => false,
            'exceptions' => true,
            'connection_timeout' => $this->timeoutSeconds,
            'cache_wsdl' => WSDL_CACHE_BOTH,
            // connection_timeout borne uniquement le TCP handshake ; stream_context
            // borne la phase de lecture (WS lent → repli sur [] après $timeoutSeconds).
            'stream_context' => stream_context_create(['http' => ['timeout' => $this->timeoutSeconds]]),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function parseResponse(mixed $response): array
    {
        $payload = $response->return ?? $response;

        $errorCode = (string) ($payload->errorCode ?? '0');
        if ($errorCode !== '0' && $errorCode !== '') {
            $message = (string) ($payload->errorMessage ?? 'unknown');
            throw new PickupPointException("Chronopost pickup API error [{$errorCode}]: {$message}");
        }

        // 0 = « mauvaise qualité, résultat à ignorer » (doc §2.4.2.b). 1 = recherche
        // faite sur le code postal, 2 = sur l'adresse. Absent = ancien format, on
        // garde le résultat.
        if (isset($payload->qualiteReponse) && (string) $payload->qualiteReponse === '0') {
            throw new PickupPointException('Chronopost pickup: low quality response (qualiteReponse=0), result ignored');
        }

        $rawPoints = $payload->listePointRelais ?? [];

        if (is_object($rawPoints)) {
            $rawPoints = [$rawPoints];
        }

        if (! is_array($rawPoints)) {
            return [];
        }

        $points = [];
        foreach ($rawPoints as $point) {
            if (! is_object($point)) {
                continue;
            }

            // Point désactivé côté Chronopost : ne jamais le proposer au client.
            if (isset($point->actif) && ($point->actif === false || $point->actif === 'false')) {
                continue;
            }

            $schedule = $this->parseOpeningSchedule($point);

            $points[] = [
                'id' => (string) ($point->identifiant ?? ''),
                'name' => (string) ($point->nom ?? ''),
                'address1' => (string) ($point->adresse1 ?? ''),
                'postcode' => (string) ($point->codePostal ?? ''),
                'city' => (string) ($point->localite ?? ''),
                'country_code' => (string) ($point->codePays ?? 'FR'),
                'distance_km' => isset($point->distanceEnMetre)
                    ? round((float) $point->distanceEnMetre / 1000, 2)
                    : null,
                'latitude' => isset($point->coordGeolocalisationLatitude)
                    ? (float) str_replace(',', '.', (string) $point->coordGeolocalisationLatitude)
                    : null,
                'longitude' => isset($point->coordGeolocalisationLongitude)
                    ? (float) str_replace(',', '.', (string) $point->coordGeolocalisationLongitude)
                    : null,
                'opening_hours' => $schedule !== null ? $this->formatOpeningHours($schedule) : null,
                'opening_schedule' => $schedule,
                'max_weight_kg' => isset($point->poidsMaxi) && is_numeric($point->poidsMaxi)
                    ? (float) $point->poidsMaxi
                    : null,
            ];
        }

        return $points;
    }

    /**
     * Horaires d'ouverture, triés du lundi au dimanche.
     *
     * Structure réelle (doc §2.4.2.b) : un `listeHoraireOuverture` par jour ouvert,
     * portant `jour` (1 = lundi … 7 = dimanche), `horairesAsString`
     * (« 08:15-12:00 12:00-17:00 ») et des plages `listeHoraireOuverture{debut, fin}`.
     * SoapClient rend un objet au lieu d'un tableau quand il n'y a qu'un élément,
     * aux deux niveaux. Un jour absent est un jour fermé.
     *
     * Retour :
     * - `[]`   → aucune contrainte horaire : consigne en accès libre (cf. doc) ;
     * - `null` → horaires présents mais illisibles (on n'affiche rien plutôt que
     *   d'annoncer à tort un accès libre) ;
     * - sinon la liste des jours ouverts.
     *
     * @return list<array{day: int, label: string, hours: string}>|null
     */
    private function parseOpeningSchedule(object $point): ?array
    {
        $days = $this->asList($point->listeHoraireOuverture ?? null);
        if ($days === []) {
            return [];
        }

        $schedule = [];
        foreach ($days as $entry) {
            if (! is_object($entry)) {
                continue;
            }

            $day = (int) ($entry->jour ?? 0);
            if (! isset(self::DAY_LABELS[$day])) {
                continue;
            }

            $hours = trim((string) preg_replace('/\s+/', ' ', (string) ($entry->horairesAsString ?? '')));
            if ($hours === '') {
                $slots = [];
                foreach ($this->asList($entry->listeHoraireOuverture ?? null) as $slot) {
                    $start = trim((string) ($slot->debut ?? ''));
                    $end = trim((string) ($slot->fin ?? ''));
                    if ($start !== '' && $end !== '') {
                        $slots[] = "{$start}-{$end}";
                    }
                }
                $hours = implode(' ', $slots);
            }

            if ($hours === '') {
                continue;
            }

            $schedule[$day] = ['day' => $day, 'label' => self::DAY_LABELS[$day], 'hours' => $hours];
        }

        if ($schedule === []) {
            return null;
        }

        ksort($schedule);

        return array_values($schedule);
    }

    /**
     * Résumé lisible : les jours consécutifs aux horaires identiques sont regroupés
     * (« Lun–Ven 08:15-12:00 12:00-17:00 · Sam 08:15-12:00 »).
     *
     * @param  list<array{day: int, label: string, hours: string}>  $schedule
     */
    private function formatOpeningHours(array $schedule): ?string
    {
        if ($schedule === []) {
            return null;
        }

        /** @var list<array{first: array{day: int, label: string}, last: array{day: int, label: string}, hours: string}> $groups */
        $groups = [];
        foreach ($schedule as $entry) {
            $lastIndex = count($groups) - 1;
            if ($lastIndex >= 0
                && $groups[$lastIndex]['hours'] === $entry['hours']
                && $groups[$lastIndex]['last']['day'] === $entry['day'] - 1
            ) {
                $groups[$lastIndex]['last'] = $entry;

                continue;
            }

            $groups[] = ['first' => $entry, 'last' => $entry, 'hours' => $entry['hours']];
        }

        return implode(' · ', array_map(function (array $group): string {
            $span = $group['first']['day'] === $group['last']['day']
                ? $group['first']['label']
                : $group['first']['label'].'–'.$group['last']['label'];

            return "{$span} {$group['hours']}";
        }, $groups));
    }

    /**
     * @return list<mixed>
     */
    private function asList(mixed $value): array
    {
        if (is_object($value)) {
            return [$value];
        }

        return is_array($value) ? array_values($value) : [];
    }
}
