<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use PHPUnit\Framework\TestCase;
use Pko\ShippingChronopost\Sdk\Shipment;
use Pko\ShippingChronopost\Services\ChronopostClient;
use Pko\ShippingCommon\Dto\ShipmentRequest;
use RuntimeException;
use SoapClient;
use stdClass;

/**
 * Payload `shippingMultiParcelV4` envoyé au SDK Chronopost, comparé à l'exemple
 * officiel (doc Web Services VL3.25.10.10). Aucun appel réseau : le SoapClient est
 * remplacé par un double.
 */
class ChronopostCreateShipmentTest extends TestCase
{
    private const PASSWORD = '255562';

    public function test_chrono13_envoie_le_code_produit_a_deux_caracteres_sans_id_relais(): void
    {
        $data = $this->client()->buildLabelsData($this->request('chrono13', '01'));

        $this->assertSame('01', $data['skybillValue']['productCode']);
        $this->assertSame('0', $data['skybillValue']['service']);
        $this->assertArrayNotHasKey('idRelai', $data['refValue']);
        $this->assertSame('1', $data['shipperValue']['shipperType']);
        $this->assertSame('1', $data['recipientValue']['recipientType']);
        $this->assertSame('M', $data['shipperValue']['shipperCivility']);
        $this->assertSame('M', $data['customerValue']['customerCivility']);
        $this->assertSame('Societe Cliente', $data['recipientValue']['recipientName']);
        $this->assertSame('Jean Dupont', $data['recipientValue']['recipientName2']);
        $this->assertSame(19869502, $data['headerValue']['accountNumber']);
    }

    public function test_un_ancien_code_a_un_chiffre_est_complete(): void
    {
        $data = $this->client()->buildLabelsData($this->request('chrono13', '1'));

        $this->assertSame('01', $data['skybillValue']['productCode']);
    }

    public function test_chrono_relais_transmet_lid_du_point_et_le_client_en_name2(): void
    {
        $data = $this->client()->buildLabelsData($this->relayRequest('3847U'));

        $this->assertSame('86', $data['skybillValue']['productCode']);
        $this->assertSame('3847U', $data['refValue']['idRelai']);
        $this->assertSame('2', $data['recipientValue']['recipientType']);
        $this->assertSame('1', $data['shipperValue']['shipperType']);
        $this->assertSame('TABAC PRESSE LOTO', $data['recipientValue']['recipientName']);
        $this->assertSame('Jean Dupont', $data['recipientValue']['recipientName2']);
        $this->assertSame('0612345678', $data['recipientValue']['recipientPhone']);
        $this->assertSame('0612345678', $data['recipientValue']['recipientMobilePhone']);
        $this->assertSame('client@example.com', $data['recipientValue']['recipientEmail']);
        $this->assertSame('33000', $data['recipientValue']['recipientZipCode']);
    }

    public function test_chrono_relais_sans_identifiant_valide_echoue_explicitement(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('identifiant de point relais');

        $this->client()->buildLabelsData($this->relayRequest('PR_MONRELAIS'));
    }

    public function test_le_payload_passe_la_validation_du_sdk(): void
    {
        // useExceptions = true : le moindre champ refusé par les regex du SDK lève.
        foreach ([$this->request('chrono13', '01'), $this->relayRequest('3847U'), $this->relayRequest('611BX')] as $request) {
            $shipment = new Shipment(true);
            $shipment->loadArray($this->client()->buildLabelsData($request));

            $this->assertTrue($shipment->RFLcheck());
        }
    }

    public function test_les_textes_sont_normalises_au_format_du_ws(): void
    {
        $data = $this->client()->buildLabelsData($this->request('chrono13', '01'));

        $this->assertSame('12 rue de l Eglise', $data['shipperValue']['shipperAdress1']);
        $this->assertSame('0102030405', $data['shipperValue']['shipperPhone']);
        $this->assertSame('+33612345678', $data['recipientValue']['recipientPhone']);
        $this->assertSame(date('Y-m-d'), $data['skybillValue']['shipDate']);
        $this->assertSame(30, $data['skybillValue']['length']);
    }

    public function test_les_telephones_internationaux_valides_sont_conserves(): void
    {
        $cases = [
            '+33601234567' => '+33601234567',
            '+33701234567' => '+33701234567',
            '+33 (0)6 12 34 56 78' => '+33612345678',
            '+33 (0) 7 01 23 45 67' => '+33701234567',
            '0033 (0)6 12 34 56 78' => '+33612345678',
            '+330612345678' => '+33612345678',
            // Italie : le 0 de l'indicatif de zone est significatif (Rome = 06).
            '+39 06 1234 5678' => '+390612345678',
            '0039 06 1234 5678' => '+390612345678',
            '06 12 34 56 78' => '0612345678',
        ];

        foreach ($cases as $input => $expected) {
            $data = $this->client()->buildLabelsData($this->request('chrono13', '01', ['phone' => $input]));

            $this->assertSame($expected, $data['recipientValue']['recipientPhone'], "Téléphone « {$input} »");
        }

        $shipment = new Shipment(true);
        $shipment->loadArray($this->client()->buildLabelsData($this->request('chrono13', '01', ['phone' => '+39 06 1234 5678'])));
        $this->assertTrue($shipment->RFLcheck());
    }

    public function test_create_shipment_renvoie_le_numero_et_le_pdf(): void
    {
        $soap = $this->fakeSoap(errorCode: 0);
        $response = $this->client($soap)->createShipment($this->relayRequest('611BX'));

        $this->assertSame('XS486816620FR', $response->trackingNumber);
        // Le WS renvoie le PDF déjà encodé : pas de double encodage.
        $this->assertStringStartsWith('%PDF', (string) base64_decode($response->labelPdfBase64, true));

        $sent = $soap->sent;
        $this->assertSame('611BX', $sent->refValue[0]->idRelais);
        $this->assertSame('86', $sent->skybillValue[0]->productCode);
    }

    public function test_une_erreur_metier_du_ws_remonte_code_et_message_sans_mot_de_passe(): void
    {
        $soap = $this->fakeSoap(errorCode: 38, errorMessage: 'No routing found for country [FR] postCode [00000]');

        try {
            $this->client($soap)->createShipment($this->request('chrono13', '01'));
            $this->fail('Une erreur WS doit lever une RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('erreur 38', $e->getMessage());
            $this->assertStringContainsString('No routing found', $e->getMessage());
            $this->assertStringNotContainsString(self::PASSWORD, $e->getMessage());
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function client(?SoapClient $soap = null): ChronopostClient
    {
        $config = ['credentials' => ['account' => '19869502', 'password' => self::PASSWORD, 'sub_account' => '']];

        return new class($config, $soap) extends ChronopostClient
        {
            public function __construct(array $config, private readonly ?SoapClient $soap)
            {
                parent::__construct($config);
            }

            protected function makeShippingSoapClient(): SoapClient
            {
                return $this->soap ?? throw new RuntimeException('Aucun appel réseau dans ce test.');
            }
        };
    }

    private function request(string $service, string $productCode, array $recipient = [], ?string $pickupPointId = null): ShipmentRequest
    {
        return new ShipmentRequest(
            orderId: 1,
            orderReference: 'WK-2026-0001',
            weightKg: 1.2,
            serviceCode: $service,
            recipient: $recipient + [
                'name' => 'Jean Dupont',
                'company' => 'Société Cliente',
                'street' => '5 avenue Général Leclerc',
                'zip' => '75002',
                'city' => 'Paris',
                'country' => 'FR',
                'phone' => '+33 6 12 34 56 78',
                'email' => 'client@example.com',
            ],
            shipper: [
                'name' => 'Boutique Test',
                'street' => "12 rue de l'Église",
                'zip' => '54000',
                'city' => 'Nancy',
                'country' => 'FR',
                'phone' => '01 02 03 04 05',
                'email' => 'expediteur@example.com',
            ],
            pickupPointId: $pickupPointId,
            carrierProductCode: $productCode,
            dimensionsCm: ['length' => 30.0, 'width' => 20.0, 'height' => 15.0],
        );
    }

    private function relayRequest(string $pickupPointId): ShipmentRequest
    {
        // Adresse déjà substituée par CreateCarrierShipmentJob::applyPickupPoint().
        return $this->request('chrono_relais', '86', [
            'company' => 'TABAC PRESSE LOTO',
            'street' => '21 PLACE DES MARTYRS DE LA RESISTANCE',
            'zip' => '33000',
            'city' => 'BORDEAUX',
            'phone' => '06 12 34 56 78',
        ], $pickupPointId);
    }

    private function fakeSoap(int $errorCode, string $errorMessage = ''): SoapClient
    {
        return new class($errorCode, $errorMessage) extends SoapClient
        {
            public ?object $sent = null;

            public function __construct(private readonly int $errorCode, private readonly string $errorMessage) {}

            public function shippingMultiParcelV4(object $shipment): stdClass
            {
                $this->sent = $shipment;

                return (object) ['return' => (object) [
                    'errorCode' => $this->errorCode,
                    'errorMessage' => $this->errorMessage,
                    'reservationNumber' => $this->errorCode === 0 ? '684288834868166207082' : null,
                    'resultMultiParcelValue' => (object) ['skybillNumber' => 'XS486816620FR'],
                ]];
            }

            public function getReservedSkybillWithTypeAndMode(object $labels): stdClass
            {
                return (object) ['return' => (object) ['errorCode' => 0, 'skybill' => base64_encode("%PDF-1.4\n…")]];
            }

            public function __getLastResponse(): ?string
            {
                return '<return><errorCode>'.$this->errorCode.'</errorCode><errorMessage>'
                    .htmlspecialchars($this->errorMessage).'</errorMessage></return>';
            }
        };
    }
}
