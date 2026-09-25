<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Illuminate\Support\Facades\File;
use Pko\ShippingChronopost\Services\ChronopostClient;
use Pko\ShippingChronopost\Validation\SoapExchangeLog;
use Pko\ShippingChronopost\Validation\ValidationKitClientFactory;
use Pko\ShippingCommon\Dto\ShipmentRequest;
use RuntimeException;
use SoapClient;
use stdClass;
use Tests\TestCase;

/**
 * `chronopost:validation-kit` : les vrais ChronopostClient / PickupPointSoapClient
 * tournent, seul le SoapClient est remplacé par un double (aucun appel réseau).
 */
class ChronopostValidationKitCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/chronopost-kit-'.uniqid();
        config()->set('chronopost.shipper', ['name' => '', 'street' => '', 'zip' => '', 'city' => '', 'country' => 'FR', 'phone' => '', 'email' => '', 'civility' => 'M']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_genere_une_etiquette_par_combinaison_du_contrat(): void
    {
        $factory = $this->fakeFactory();
        $this->app->instance(ValidationKitClientFactory::class, $factory);

        $this->artisan('chronopost:validation-kit', ['--output' => $this->dir])->assertSuccessful();

        // 4 combinaisons produit / service, relais sur le point renvoyé par la recherche.
        $sent = array_map(fn (object $s): array => [
            $s->skybillValue[0]->productCode,
            $s->skybillValue[0]->service,
            $s->refValue[0]->idRelais ?? null,
        ], $factory->soap->shipments);
        $this->assertSame([['01', '0', null], ['01', '6', null], ['86', '0', '611BX'], ['86', '6', '611BX']], $sent);
        $this->assertSame('TABAC DE LA GARE', $factory->soap->shipments[2]->recipientValue[0]->recipientName);

        $pdfs = File::glob($this->dir.'/*.pdf');
        $this->assertCount(4, $pdfs);
        foreach ($pdfs as $pdf) {
            $this->assertStringStartsWith('%PDF', (string) File::get($pdf));
        }

        $readme = File::get($this->dir.'/README.md');
        foreach (['XN000000001FR', 'XN000000002FR', 'XS000000003FR', 'XS000000004FR', '611BX', 'compte test officiel'] as $expected) {
            $this->assertStringContainsString($expected, $readme);
        }
        $this->assertStringNotContainsString('ERREUR', $readme);

        // Traces brutes : mot de passe masqué, base64 tronqué.
        $request = File::get($this->dir.'/01-chrono13h-semaine-shippingMultiParcelV4-requete.xml');
        $this->assertStringContainsString('<password>********</password>', $request);
        $this->assertStringContainsString('<service>0</service>', $request);
        $this->assertStringContainsString('<password>********</password>', File::get($this->dir.'/00-recherche-point-relais-recherchePointChronopostInter-requete.xml'));
        foreach (File::glob($this->dir.'/*.xml') as $xml) {
            $this->assertStringNotContainsString('255562', (string) File::get($xml), basename($xml));
        }
        $label = File::get($this->dir.'/01-chrono13h-semaine-getReservedSkybillWithTypeAndMode-reponse.xml');
        $this->assertStringContainsString('base64 tronqué', $label);
    }

    public function test_une_erreur_ws_est_consignee_dans_le_readme(): void
    {
        $factory = $this->fakeFactory(failingService: '6');
        $this->app->instance(ValidationKitClientFactory::class, $factory);

        $this->artisan('chronopost:validation-kit', ['--output' => $this->dir])->assertFailed();

        $readme = File::get($this->dir.'/README.md');
        $this->assertStringContainsString('ERREUR : Chronopost shippingMultiParcelV4 : erreur 33', $readme);
        $this->assertCount(2, File::glob($this->dir.'/*.pdf'));
    }

    public function test_refuse_un_compte_autre_que_le_compte_test_sans_force(): void
    {
        $this->app->instance(ValidationKitClientFactory::class, new class extends ValidationKitClientFactory
        {
            public function soapClient(string $wsdl, array $options, SoapExchangeLog $log): SoapClient
            {
                throw new RuntimeException('Aucun appel attendu sans --force.');
            }
        });

        $this->artisan('chronopost:validation-kit', [
            '--account' => '12345678',
            '--password' => 'prod-secret',
            '--output' => $this->dir,
        ])->expectsOutputToContain('--force')->assertFailed();

        $this->assertDirectoryDoesNotExist($this->dir);
    }

    public function test_le_service_samedi_est_transmis_au_skybill(): void
    {
        $client = new ChronopostClient(['credentials' => ['account' => '19869502', 'password' => '255562']]);
        $request = fn (?string $service) => new ShipmentRequest(
            orderId: 1, orderReference: 'R1', weightKg: 1.0, serviceCode: 'chrono13',
            recipient: ['name' => 'Jean Dupont', 'street' => '1 rue A', 'zip' => '75002', 'city' => 'Paris', 'phone' => '0612345678'],
            shipper: ['name' => 'Exp', 'street' => '1 rue B', 'zip' => '69007', 'city' => 'Lyon', 'phone' => '0478000000', 'email' => 'exp@example.com'],
            carrierProductCode: '01', carrierService: $service,
        );

        $this->assertSame('0', $client->buildLabelsData($request(null))['skybillValue']['service']);
        $this->assertSame('6', $client->buildLabelsData($request('6'))['skybillValue']['service']);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function fakeFactory(?string $failingService = null): ValidationKitClientFactory
    {
        $soap = $this->fakeSoap($failingService);

        return new class($soap) extends ValidationKitClientFactory
        {
            public function __construct(public readonly SoapClient $soap) {}

            public function soapClient(string $wsdl, array $options, SoapExchangeLog $log): SoapClient
            {
                $this->soap->log = $log;

                return $this->soap;
            }
        };
    }

    private function fakeSoap(?string $failingService): SoapClient
    {
        return new class($failingService) extends SoapClient
        {
            public ?SoapExchangeLog $log = null;

            /** @var list<object> */
            public array $shipments = [];

            private int $counter = 0;

            private string $lastResponse = '';

            public function __construct(private readonly ?string $failingService) {}

            public function recherchePointChronopostInter(array $params): stdClass
            {
                $this->record('recherchePointChronopostInter', '<password>'.$params['password'].'</password>', '<errorCode>0</errorCode>');

                return (object) ['return' => (object) [
                    'errorCode' => 0,
                    'qualiteReponse' => 1,
                    'listePointRelais' => (object) [
                        'identifiant' => '611BX', 'nom' => 'TABAC DE LA GARE', 'adresse1' => '3 RUE DE LA GARE',
                        'codePostal' => '33000', 'localite' => 'BORDEAUX', 'codePays' => 'FR', 'actif' => true,
                    ],
                ]];
            }

            public function shippingMultiParcelV4(object $shipment): stdClass
            {
                $this->shipments[] = $shipment;
                $service = (string) $shipment->skybillValue[0]->service;
                $failed = $service === $this->failingService;
                $number = sprintf('%s%09dFR', $shipment->skybillValue[0]->productCode === '86' ? 'XS' : 'XN', ++$this->counter);

                $this->record(
                    'shippingMultiParcelV4',
                    '<service>'.$service.'</service><password>'.$shipment->password.'</password>',
                    '<errorCode>'.($failed ? 33 : 0).'</errorCode><errorMessage>'.($failed ? 'Service incorrect' : '').'</errorMessage>',
                );

                return (object) ['return' => (object) [
                    'errorCode' => $failed ? 33 : 0,
                    'errorMessage' => $failed ? 'Service incorrect' : '',
                    'reservationNumber' => $failed ? null : 'R'.$this->counter,
                    'resultMultiParcelValue' => (object) ['skybillNumber' => $number],
                ]];
            }

            public function getReservedSkybillWithTypeAndMode(object $labels): stdClass
            {
                $pdf = base64_encode("%PDF-1.4\n".str_repeat('x', 400));
                $this->record('getReservedSkybillWithTypeAndMode', '<reservationNumber>R</reservationNumber>', '<skybill>'.$pdf.'</skybill>');

                return (object) ['return' => (object) ['errorCode' => 0, 'skybill' => $pdf]];
            }

            public function __getLastResponse(): ?string
            {
                return $this->lastResponse;
            }

            private function record(string $method, string $body, string $response): void
            {
                $this->lastResponse = "<return>{$response}</return>";
                $this->log?->record(
                    "<env:Envelope xmlns:env=\"e\"><env:Body><ns1:{$method} xmlns:ns1=\"n\">{$body}</ns1:{$method}></env:Body></env:Envelope>",
                    "<env:Envelope xmlns:env=\"e\"><env:Body><ns1:{$method}Response xmlns:ns1=\"n\"><return>{$response}</return></ns1:{$method}Response></env:Body></env:Envelope>",
                );
            }
        };
    }
}
