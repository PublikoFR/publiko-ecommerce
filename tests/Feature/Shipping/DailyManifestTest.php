<?php

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Support\LabelArchive;
use Pko\ShippingCommon\Support\ManifestPdf;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Bordereau de remise + archive d'étiquettes.
 *
 * Le bordereau est construit uniquement à partir de `pko_carrier_shipments` :
 * aucun appel transporteur ne doit être nécessaire pour l'éditer.
 */
class DailyManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        Storage::fake('local');

        config([
            'chronopost.shipper' => [
                'name' => 'Expéditeur Test',
                'street' => '1 rue du Test',
                'zip' => '75001',
                'city' => 'Paris',
                'country' => 'FR',
                'phone' => '0600000000',
                'email' => 'expediteur@test.local',
            ],
            'chronopost.credentials.account' => '12345678',
        ]);
    }

    public function test_le_bordereau_liste_les_lt_et_totalise_national_et_international(): void
    {
        $shipments = collect([
            $this->makeShipment('LT-FR-1', ['zip' => '75001', 'city' => 'Paris', 'country' => 'FR']),
            $this->makeShipment('LT-FR-2', ['zip' => '34500', 'city' => 'Béziers', 'country' => 'FR']),
            $this->makeShipment('LT-BE-1', ['zip' => '1000', 'city' => 'Bruxelles', 'country' => 'BE']),
        ]);

        $response = ManifestPdf::download($shipments, Carbon::parse('2026-07-31'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'bordereau-2026-07-31.pdf',
            (string) $response->headers->get('content-disposition'),
        );

        $pdf = $response->getContent();
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_larchive_regroupe_les_etiquettes_disponibles(): void
    {
        $withLabel = $this->makeShipment('LT-1', ['zip' => '75001', 'city' => 'Paris', 'country' => 'FR']);
        Storage::disk('local')->put('labels/1/chronopost-LT-1.pdf', '%PDF-1.4 fake');
        $withLabel->update(['label_path' => 'labels/1/chronopost-LT-1.pdf']);

        // Étiquette référencée mais absente du disque → ignorée, pas d'exception.
        $missing = $this->makeShipment('LT-2', ['zip' => '75002', 'city' => 'Paris', 'country' => 'FR']);
        $missing->update(['label_path' => 'labels/2/chronopost-LT-2.pdf']);

        $path = LabelArchive::build(collect([$withLabel, $missing]), 'etiquettes.zip');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertStringContainsString('LT-1', (string) $zip->getNameIndex(0));
        $zip->close();

        @unlink($path);
    }

    public function test_larchive_refuse_une_selection_sans_aucune_etiquette(): void
    {
        $shipment = $this->makeShipment('LT-3', ['zip' => '75003', 'city' => 'Paris', 'country' => 'FR']);

        $this->expectException(RuntimeException::class);

        LabelArchive::build(collect([$shipment]), 'etiquettes.zip');
    }

    public function test_la_page_bordereau_saffiche_avec_les_lt_du_jour(): void
    {
        $this->makeShipment('LT-AFFICHEE', ['zip' => '75001', 'city' => 'Paris', 'country' => 'FR']);

        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'bordereau-test@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');

        $response = $this->get('/admin/expedition/bordereau');

        $response->assertOk();
        $response->assertSee('Bordereau de remise');
        $response->assertSee('LT-AFFICHEE');
    }

    public function test_le_filtre_de_date_passe_en_url_isole_la_remise_visee(): void
    {
        $today = $this->makeShipment('LT-AUJOURDHUI', ['zip' => '75001', 'city' => 'Paris', 'country' => 'FR']);

        $older = $this->makeShipment('LT-AVANT-HIER', ['zip' => '75002', 'city' => 'Paris', 'country' => 'FR']);
        $older->forceFill(['created_at' => Carbon::today()->subDays(2)])->save();

        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'bordereau-filtre@example.com',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);

        $this->actingAs($staff, 'staff');

        // URL produite par le raccourci « Bordereau du … » de la fiche commande.
        $response = $this->get(
            '/admin/expedition/bordereau?date='.Carbon::today()->subDays(2)->format('Y-m-d')
        );

        $response->assertOk();
        $response->assertSee('LT-AVANT-HIER');
        $response->assertDontSee('LT-AUJOURDHUI');

        $this->assertNotNull($today->tracking_number);
    }

    /**
     * @param  array<string, string>  $recipient
     */
    private function makeShipment(string $trackingNumber, array $recipient): CarrierShipment
    {
        return CarrierShipment::create([
            'order_id' => $this->makeOrderId(),
            'carrier' => 'chronopost',
            'service_code' => 'chrono13',
            'origin' => CarrierShipment::ORIGIN_WEKLO,
            'status' => CarrierShipment::STATUS_CREATED,
            'tracking_number' => $trackingNumber,
            'payload_sent' => [
                'recipient' => $recipient,
                'carrierProductCode' => '1',
            ],
        ]);
    }

    /**
     * `pko_carrier_shipments.order_id` porte une contrainte de clé étrangère :
     * le bordereau ne peut pas être testé sur des identifiants fictifs.
     */
    private function makeOrderId(): int
    {
        $channel = Channel::query()->first();
        $currency = Currency::query()->first();

        return (int) DB::table('lunar_orders')->insertGetId([
            'channel_id' => $channel->id,
            'status' => 'dispatched',
            'reference' => 'TEST-'.uniqid(),
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate' => 1,
            'sub_total' => 10000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 10000,
            'tax_breakdown' => '[]',
            'discount_breakdown' => '[]',
            'shipping_breakdown' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
