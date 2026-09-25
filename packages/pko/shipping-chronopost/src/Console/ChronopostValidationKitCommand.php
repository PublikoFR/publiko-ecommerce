<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Pko\ShippingChronopost\Validation\SoapExchangeLog;
use Pko\ShippingChronopost\Validation\SoapTraceSanitizer;
use Pko\ShippingChronopost\Validation\ValidationKitClientFactory;
use Pko\ShippingCommon\Dto\ShipmentRequest;
use Throwable;

/**
 * Kit de validation « mise en production » exigé par Chronopost : une étiquette par
 * combinaison produit/service du contrat, avec les requêtes SOAP brutes (mot de passe
 * masqué), les réponses (base64 tronqué), les PDF et un README récapitulatif.
 *
 * N'envoie rien à l'extérieur hormis les appels au WS Chronopost : le kit est joint au
 * mail du support à la main.
 */
class ChronopostValidationKitCommand extends Command
{
    /** Compte test officiel (doc WS VL3.25.10.10, mail d'ouverture de compte) — non secret. */
    public const TEST_ACCOUNT = '19869502';

    public const TEST_PASSWORD = '255562';

    /**
     * Combinaisons produit / service du contrat utilisées par la boutique.
     *
     * @var list<array{slug: string, label: string, product: string, service: string, relay: bool}>
     */
    public const COMBINATIONS = [
        ['slug' => 'chrono13h-semaine', 'label' => 'Chrono 13H — livraison semaine', 'product' => '01', 'service' => '0', 'relay' => false],
        ['slug' => 'chrono13h-samedi', 'label' => 'Chrono 13H — livraison samedi', 'product' => '01', 'service' => '6', 'relay' => false],
        ['slug' => 'relais13h-semaine', 'label' => 'Chrono Relais 13H — livraison semaine', 'product' => '86', 'service' => '0', 'relay' => true],
        ['slug' => 'relais13h-samedi', 'label' => 'Chrono Relais 13H — livraison samedi', 'product' => '86', 'service' => '6', 'relay' => true],
    ];

    /** Destinataire fictif des étiquettes de test (hors relais). */
    private const TEST_RECIPIENT = [
        'name' => 'Destinataire Test',
        'company' => '',
        'street' => '5 avenue Anatole France',
        'zip' => '75007',
        'city' => 'Paris',
        'country' => 'FR',
        'phone' => '0612345678',
        'email' => 'destinataire.test@example.com',
    ];

    /** Complète les champs expéditeur absents de config('chronopost.shipper'). */
    private const FALLBACK_SHIPPER = [
        'name' => 'Expediteur Test',
        'street' => '1 rue de la Paix',
        'zip' => '75002',
        'city' => 'Paris',
        'country' => 'FR',
        'phone' => '0102030405',
        'email' => 'expediteur.test@example.com',
        'civility' => 'M',
    ];

    protected $signature = 'chronopost:validation-kit
        {--account= : Numéro de compte Chronopost (défaut : compte test 19869502)}
        {--password= : Mot de passe associé à --account}
        {--sub-account= : Sous-compte (optionnel)}
        {--from-config : Identifiants lus dans le coffre secret() puis config(\'chronopost.credentials\')}
        {--zip=33000 : Code postal de la recherche de point relais}
        {--city=BORDEAUX : Ville de la recherche de point relais}
        {--output= : Dossier de sortie (défaut : storage/app/chronopost-validation/<date>)}
        {--force : Autorise un compte autre que le compte test (génère de VRAIES étiquettes)}';

    protected $description = 'Génère le kit d\'étiquettes test Chronopost (une par produit/service du contrat) à envoyer au support pour obtenir les accès de production.';

    public function handle(ValidationKitClientFactory $factory): int
    {
        $credentials = $this->resolveCredentials();
        if ($credentials === null) {
            return self::FAILURE;
        }

        $isTestAccount = $credentials['account'] === self::TEST_ACCOUNT;
        if (! $isTestAccount && ! $this->option('force')) {
            $this->error(sprintf(
                'Le compte %s n\'est pas le compte test %s : les étiquettes seraient de VRAIES lettres de transport. Relancer avec --force pour confirmer.',
                $credentials['account'],
                self::TEST_ACCOUNT,
            ));

            return self::FAILURE;
        }

        $now = Carbon::now();
        $dir = (string) ($this->option('output') ?: storage_path('app/chronopost-validation/'.$now->format('Y-m-d')));
        File::ensureDirectoryExists($dir);

        [$shipper, $shipperFallbacks] = $this->shipper();
        $log = new SoapExchangeLog;
        $files = [];

        $this->info("Kit de validation Chronopost → {$dir}");

        // 1. Point relais réel pour les produits Relais.
        $zip = (string) $this->option('zip');
        $city = (string) $this->option('city');
        $point = null;
        $pointError = null;
        try {
            $points = $factory->pickup(['account' => $credentials['account'], 'password' => $credentials['password']], $log)
                ->search($zip, 'FR', '86', $city);
            $point = $points[0] ?? null;
            if ($point === null) {
                $pointError = "aucun point relais renvoyé pour {$zip} {$city}";
            }
        } catch (Throwable $e) {
            $pointError = $e->getMessage();
        }
        $files = [...$files, ...$this->writeExchanges($dir, '00-recherche-point-relais', $log->take())];
        $this->line($point !== null
            ? sprintf('  Point relais : %s — %s, %s %s', $point['id'], $point['name'], $point['postcode'], $point['city'])
            : "  <error>Point relais introuvable : {$pointError}</error>");

        // 2. Une étiquette par combinaison du contrat.
        $clientConfig = [
            ...(array) config('chronopost', []),
            'credentials' => $credentials,
            'label_format' => 'PDF',
        ];
        $client = $factory->shipping($clientConfig, $log);

        $results = [];
        foreach (self::COMBINATIONS as $i => $combo) {
            $prefix = sprintf('%02d-%s', $i + 1, $combo['slug']);
            $result = ['combo' => $combo, 'tracking' => null, 'pdf' => null, 'pdf_bytes' => 0, 'error' => null];

            if ($combo['relay'] && $point === null) {
                $result['error'] = "point relais indisponible ({$pointError})";
                $results[] = $result;
                $this->line("  <error>✗ {$combo['label']} : {$result['error']}</error>");

                continue;
            }

            try {
                $response = $client->createShipment($this->request($i + 1, $combo, $shipper, $point));
                $result['tracking'] = $response->trackingNumber;

                $pdf = base64_decode($response->labelPdfBase64, true);
                if (! is_string($pdf) || $pdf === '' || ! str_starts_with($pdf, '%PDF')) {
                    $result['error'] = 'étiquette reçue invalide (PDF vide ou sans en-tête %PDF)';
                } else {
                    $pdfFile = "{$prefix}-{$response->trackingNumber}.pdf";
                    File::put($dir.'/'.$pdfFile, $pdf);
                    $result['pdf'] = $pdfFile;
                    $result['pdf_bytes'] = strlen($pdf);
                    $files[] = $pdfFile;
                }
            } catch (Throwable $e) {
                $result['error'] = $e->getMessage();
            }

            $files = [...$files, ...$this->writeExchanges($dir, $prefix, $log->take())];
            $results[] = $result;

            $this->line($result['error'] === null
                ? "  <info>✓</info> {$combo['label']} : LT {$result['tracking']} ({$result['pdf_bytes']} octets)"
                : "  <error>✗ {$combo['label']} : {$result['error']}</error>");
        }

        File::put($dir.'/README.md', $this->readme($now, $credentials, $isTestAccount, $shipper, $shipperFallbacks, $point, $pointError, $results, $files));

        $failures = count(array_filter($results, fn (array $r): bool => $r['error'] !== null));
        $this->newLine();
        $this->line("README : {$dir}/README.md");

        if ($failures > 0) {
            $this->warn("{$failures} combinaison(s) en échec — détail dans le README.");

            return self::FAILURE;
        }

        $this->info('Kit complet. À joindre au mail du support Chronopost (aucun envoi automatique).');

        return self::SUCCESS;
    }

    /**
     * @return array{account: string, password: string, sub_account: string}|null
     */
    private function resolveCredentials(): ?array
    {
        if ($this->option('account') !== null) {
            $account = trim((string) $this->option('account'));
            $password = (string) $this->option('password');
            $subAccount = (string) $this->option('sub-account');
        } elseif ($this->option('from-config')) {
            $account = (string) (secret('chronopost.account') ?? config('chronopost.credentials.account'));
            $password = (string) (secret('chronopost.password') ?? config('chronopost.credentials.password'));
            $subAccount = (string) (secret('chronopost.sub_account') ?? config('chronopost.credentials.sub_account'));
        } else {
            $account = self::TEST_ACCOUNT;
            $password = self::TEST_PASSWORD;
            $subAccount = '';
        }

        if ($account === '' || $password === '') {
            $this->error('Identifiants Chronopost incomplets (compte et mot de passe requis).');

            return null;
        }

        return ['account' => $account, 'password' => $password, 'sub_account' => $subAccount];
    }

    /**
     * Expéditeur de config('chronopost.shipper'), complété champ par champ.
     *
     * @return array{0: array<string, string>, 1: list<string>}
     */
    private function shipper(): array
    {
        $configured = (array) config('chronopost.shipper', []);
        $shipper = [];
        $fallbacks = [];

        foreach (self::FALLBACK_SHIPPER as $key => $default) {
            $value = trim((string) ($configured[$key] ?? ''));
            if ($value === '') {
                $value = $default;
                $fallbacks[] = $key;
            }
            $shipper[$key] = $value;
        }

        return [$shipper, $fallbacks];
    }

    /**
     * @param  array{slug: string, label: string, product: string, service: string, relay: bool}  $combo
     * @param  array<string, string>  $shipper
     * @param  array<string, mixed>|null  $point
     */
    private function request(int $rank, array $combo, array $shipper, ?array $point): ShipmentRequest
    {
        $recipient = self::TEST_RECIPIENT;
        if ($combo['relay'] && $point !== null) {
            // Même substitution que CreateCarrierShipmentJob::applyPickupPoint() :
            // l'adresse est celle du point, le nom du point en raison sociale.
            $recipient = [
                ...$recipient,
                'company' => (string) $point['name'],
                'street' => (string) $point['address1'],
                'zip' => (string) $point['postcode'],
                'city' => (string) $point['city'],
                'country' => (string) ($point['country_code'] ?: 'FR'),
            ];
        }

        return new ShipmentRequest(
            orderId: 0,
            orderReference: sprintf('VALIDATION-%02d', $rank),
            weightKg: 1.0,
            serviceCode: $combo['relay'] ? 'chrono_relais' : 'chrono13',
            recipient: $recipient,
            shipper: $shipper,
            pickupPointId: $combo['relay'] ? (string) $point['id'] : null,
            carrierProductCode: $combo['product'],
            dimensionsCm: ['length' => 30.0, 'width' => 20.0, 'height' => 15.0],
            carrierService: $combo['service'],
        );
    }

    /**
     * @param  list<array{method: string, request: string, response: string}>  $exchanges
     * @return list<string>
     */
    private function writeExchanges(string $dir, string $prefix, array $exchanges): array
    {
        $files = [];
        foreach ($exchanges as $exchange) {
            foreach (['request' => 'requete', 'response' => 'reponse'] as $key => $suffix) {
                $file = "{$prefix}-{$exchange['method']}-{$suffix}.xml";
                File::put($dir.'/'.$file, SoapTraceSanitizer::sanitize($exchange[$key]));
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param  array{account: string, password: string, sub_account: string}  $credentials
     * @param  array<string, string>  $shipper
     * @param  list<string>  $shipperFallbacks
     * @param  array<string, mixed>|null  $point
     * @param  list<array<string, mixed>>  $results
     * @param  list<string>  $files
     */
    private function readme(
        Carbon $now,
        array $credentials,
        bool $isTestAccount,
        array $shipper,
        array $shipperFallbacks,
        ?array $point,
        ?string $pointError,
        array $results,
        array $files,
    ): string {
        $lines = [
            '# Kit de validation Web Services Chronopost',
            '',
            sprintf('- Généré le : %s (date d\'expédition `shipDate` = %s, %s)', $now->format('d/m/Y H:i'), $now->format('Y-m-d'), $now->locale('fr')->isoFormat('dddd')),
            sprintf('- Compte : `%s`%s — mot de passe masqué dans les requêtes', $credentials['account'], $isTestAccount ? ' (compte test officiel)' : ' (compte de PRODUCTION)'),
            '- Méthode d\'étiquetage : `shippingMultiParcelV4` (ShippingServiceWS), étiquette récupérée par `getReservedSkybillWithTypeAndMode`, format PDF',
            sprintf('- Expéditeur : %s, %s %s %s%s', $shipper['name'], $shipper['street'], $shipper['zip'], $shipper['city'],
                $shipperFallbacks !== [] ? ' — champs de test utilisés faute de configuration : '.implode(', ', $shipperFallbacks) : ''),
            $point !== null
                ? sprintf('- Point relais (`recherchePointChronopostInter`, produit 86) : `%s` — %s, %s %s %s', $point['id'], $point['name'], $point['address1'], $point['postcode'], $point['city'])
                : "- Point relais : introuvable ({$pointError})",
            '',
            '## Étiquettes',
            '',
            '| Produit | productCode | service | N° de LT | PDF | Statut |',
            '|---|---|---|---|---|---|',
        ];

        foreach ($results as $r) {
            $lines[] = sprintf(
                '| %s | `%s` | `%s` | %s | %s | %s |',
                $r['combo']['label'],
                $r['combo']['product'],
                $r['combo']['service'],
                $r['tracking'] !== null ? '`'.$r['tracking'].'`' : '—',
                $r['pdf'] !== null ? sprintf('`%s` (%d octets)', $r['pdf'], $r['pdf_bytes']) : '—',
                $r['error'] === null ? 'OK' : 'ERREUR : '.str_replace(['|', "\n"], ['\\|', ' '], (string) $r['error']),
            );
        }

        $lines = [...$lines,
            '',
            'Service `6` (samedi) : livraison le samedi pour un colis remis le vendredi (doc VL3.25.10.10 §3.1 / §3.2).',
            '',
            '## Fichiers',
            '',
            'Pour chaque étiquette : requête et réponse SOAP brutes de chaque appel (`*-requete.xml` / `*-reponse.xml` — mot de passe remplacé par `'.SoapTraceSanitizer::PASSWORD_MASK.'`, contenu base64 tronqué) et le PDF décodé.',
            '',
            ...array_map(fn (string $f): string => "- `{$f}`", $files),
            '',
            '## Envoi',
            '',
            'À joindre au mail adressé au support Web Services Chronopost pour obtenir les identifiants de production. Aucun envoi automatique.',
            '',
        ];

        return implode("\n", $lines);
    }
}
