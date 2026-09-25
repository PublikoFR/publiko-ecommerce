<?php

declare(strict_types=1);

namespace Tests\Unit\Shipping;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pko\ShippingChronopost\Exceptions\QuickCostException;
use Pko\ShippingChronopost\Services\QuickCostSoapClient;
use SoapClient;

class QuickCostSoapClientTest extends TestCase
{
    /**
     * Stub SOAP renvoyant un payload `quickCostV3Response` et capturant la requête.
     */
    private function stub(object $return): SoapClient
    {
        return new class($return) extends SoapClient
        {
            /** @var array<string, mixed>|null */
            public ?array $lastArgs = null;

            public function __construct(private object $return) {}

            public function quickCostV3($args): object
            {
                $this->lastArgs = $args;

                return (object) ['return' => $this->return];
            }
        };
    }

    private function client(SoapClient $soap): QuickCostSoapClient
    {
        return new QuickCostSoapClient(
            credentials: ['account' => '19869502', 'password' => '255562'],
            client: $soap,
        );
    }

    /**
     * Réponse réaliste (forme observée sur le WS réel, montants d'un compte tarifé).
     */
    private function realisticReturn(float $ht, float $ttc, float $tva): object
    {
        return (object) [
            'amount' => $ht,
            'amountTTC' => $ttc,
            'amountTVA' => $tva,
            'errorCode' => 0,
            'service' => [
                (object) ['amount' => 0.22, 'amountTTC' => 0.26, 'amountTVA' => 0.04, 'codeService' => 'ER', 'label' => 'Participation Eco-Responsable'],
            ],
            'zone' => 'NT',
            'assurance' => (object) ['plafond' => 0.0, 'taux' => 0.0],
            'cap' => (object) ['capAvion' => 0.4225, 'capRoute' => 0.228],
        ];
    }

    public function test_missing_credentials_throws(): void
    {
        $client = new QuickCostSoapClient(['account' => '', 'password' => '']);

        $this->expectException(QuickCostException::class);
        $this->expectExceptionMessageMatches('/credentials missing/i');

        $client->quickCost('1', 3.5, '69007', '75001');
    }

    public function test_amount_is_ht_and_amount_ttc_is_ttc(): void
    {
        $soap = $this->stub($this->realisticReturn(12.42, 14.90, 2.48));

        $response = $this->client($soap)->quickCost('1', 3.5, '69007', '75001');

        $this->assertSame('1', $response->serviceCode);
        $this->assertSame(1242, $response->priceCentsHT);
        $this->assertSame(1490, $response->priceCentsTTC);
        $this->assertSame(248, $response->priceCentsTVA);
        $this->assertSame('EUR', $response->currency);
        $this->assertSame('NT', $response->zone);
    }

    public function test_request_matches_wsdl_type_without_country_fields(): void
    {
        $soap = $this->stub($this->realisticReturn(10.0, 12.0, 2.0));

        $this->client($soap)->quickCost('86', 2.0, '28500', '75001');

        $this->assertSame([
            'accountNumber' => '19869502',
            'password' => '255562',
            'depCode' => '28500',
            'arrCode' => '75001',
            'weight' => 2.0,
            'productCode' => '86',
            'type' => 'M',
        ], $soap->lastArgs);
    }

    public function test_ttc_derived_from_ht_plus_tva_when_amount_ttc_absent(): void
    {
        $soap = $this->stub((object) ['amount' => 10.0, 'amountTVA' => 2.0, 'errorCode' => 0]);

        $response = $this->client($soap)->quickCost('1', 1.0, '69007', '75001');

        $this->assertSame(1000, $response->priceCentsHT);
        $this->assertSame(1200, $response->priceCentsTTC);
    }

    public function test_zero_amount_without_error_code_is_rejected(): void
    {
        // Comportement réel du compte de test : errorCode=0 mais montants à 0.0.
        $soap = $this->stub($this->realisticReturn(0.0, 0.0, 0.0));

        $this->expectException(QuickCostException::class);
        $this->expectExceptionMessageMatches('/no usable amount for product 1/');

        $this->client($soap)->quickCost('1', 3.5, '69007', '75001');
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function errorCodes(): array
    {
        return [
            'system' => [1, 'System Error', 'erreur système'],
            'data empty' => [2, 'Data Empty', 'paramètre obligatoire manquant'],
            'password' => [3, 'invalid account or password', 'mot de passe ne correspondant pas'],
            'product' => [4, 'Invalid product code for this destination', 'code produit incohérent'],
            'amount' => [5, 'Amount not found', 'aucun tarif trouvé'],
        ];
    }

    #[DataProvider('errorCodes')]
    public function test_documented_error_codes_have_explicit_messages(int $code, string $apiMessage, string $expected): void
    {
        $soap = $this->stub((object) [
            'amount' => 0.0,
            'amountTTC' => 0.0,
            'amountTVA' => 0.0,
            'errorCode' => $code,
            'errorMessage' => $apiMessage,
        ]);

        try {
            $this->client($soap)->quickCost('1', 3.5, '69007', '75001');
            $this->fail('QuickCostException attendue');
        } catch (QuickCostException $e) {
            $this->assertSame($code, $e->getCode());
            $this->assertStringContainsString("error [{$code}]", $e->getMessage());
            $this->assertStringContainsString($expected, $e->getMessage());
            $this->assertStringContainsString($apiMessage, $e->getMessage());
        }
    }

    public function test_unknown_error_code_still_throws(): void
    {
        $soap = $this->stub((object) ['errorCode' => 23, 'errorMessage' => 'Invalid account']);

        $this->expectException(QuickCostException::class);
        $this->expectExceptionMessageMatches('/error \[23\].*Invalid account/');

        $this->client($soap)->quickCost('1', 3.5, '69007', '75001');
    }
}
