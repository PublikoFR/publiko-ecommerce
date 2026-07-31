<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire les 3 méthodes table-rate héritées du seed de démarrage.
 *
 * Au lot L1 (refonte frais de port 2026), `ShippingPlugin` a été retiré du panel
 * Filament au motif que « lunar_customer_group_shipping_method est vide, donc
 * aucune option ne sort au checkout ». Ce postulat était faux : `PkoShippingSeeder`
 * appelait `scheduleCustomerGroup()` sur les 3 méthodes. Résultat sur toute base
 * passée par `make fresh` : « Livraison standard » (6,90 €), « Retrait entrepôt »
 * et « Livraison offerte » s'affichaient au checkout à côté des services
 * Chronopost — sans aucune UI pour les gérer, et en doublon du franco déjà
 * calculé par `UnifiedShippingModifier`.
 *
 * On désactive les méthodes ET on détache leur planning de groupes clients :
 * `ShippingRateResolver` rejette alors leurs rates, ce qui rend enfin vrai le
 * postulat de L1. Les lignes ne sont pas supprimées — réactivation possible via
 * `enabled=1` + `scheduleCustomerGroup()`.
 */
return new class extends Migration
{
    private const LEGACY_CODES = ['pko-standard', 'pko-pickup', 'pko-free'];

    public function up(): void
    {
        // Le package table-rate reste installé, mais ses migrations peuvent ne pas
        // avoir tourné sur une base neuve : on ne présume rien.
        if (! Schema::hasTable('lunar_shipping_methods')) {
            return;
        }

        $ids = DB::table('lunar_shipping_methods')
            ->whereIn('code', self::LEGACY_CODES)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('lunar_shipping_methods')
            ->whereIn('id', $ids)
            ->update(['enabled' => false]);

        if (Schema::hasTable('lunar_customer_group_shipping_method')) {
            DB::table('lunar_customer_group_shipping_method')
                ->whereIn('shipping_method_id', $ids)
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('lunar_shipping_methods')) {
            return;
        }

        // On réactive les méthodes, sans reconstruire le planning de groupes :
        // le rétablir ferait réapparaître les options au checkout, ce qui n'est
        // pas ce qu'on attend d'un simple rollback. Il se repeuple avec
        // `scheduleCustomerGroup()` si on veut vraiment les remettre en service.
        DB::table('lunar_shipping_methods')
            ->whereIn('code', self::LEGACY_CODES)
            ->update(['enabled' => true]);
    }
};
