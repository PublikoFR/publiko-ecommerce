<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the carrier-side product code to pko_carrier_services.
 *
 * `service_code` is our internal identifier (chrono13, chrono_relais…) — it is what
 * the checkout stores in `shipping_option` and what the pricing grids key on. The
 * carrier web services expect a completely different value (`1`, `2`, `86`…), and
 * until now the internal slug was sent verbatim as `skybillValue.productCode`, so
 * every label creation call failed.
 *
 * NULL means "use service_code as-is", which is correct for Colissimo (DOM / DOS are
 * already the real product codes).
 *
 * Chronopost values taken from the official PrestaShop module v7.5.6
 * (`Chronopost::$carriersDefinitions`, standard account).
 */
return new class extends Migration
{
    private const CHRONOPOST_PRODUCT_CODES = [
        'chrono_relais' => '86',
        'chrono13' => '1',
        'chrono10' => '2',
        'chrono18' => '16',
        'chrono_classic' => '44',
    ];

    public function up(): void
    {
        Schema::table('pko_carrier_services', function (Blueprint $table) {
            $table->string('carrier_product_code', 8)
                ->nullable()
                ->after('service_code')
                ->comment('Code produit attendu par le WS transporteur. NULL = service_code utilisé tel quel.');
        });

        foreach (self::CHRONOPOST_PRODUCT_CODES as $serviceCode => $productCode) {
            DB::table('pko_carrier_services')
                ->where('carrier_code', 'chronopost')
                ->where('service_code', $serviceCode)
                ->update(['carrier_product_code' => $productCode]);
        }
    }

    public function down(): void
    {
        Schema::table('pko_carrier_services', function (Blueprint $table) {
            $table->dropColumn('carrier_product_code');
        });
    }
};
