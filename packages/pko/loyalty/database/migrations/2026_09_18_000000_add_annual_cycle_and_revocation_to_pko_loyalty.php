<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cycle annuel des points (remise à zéro au 1er janvier) + retrait de points
 * sur remboursement.
 *
 * - `customer_points.points_year` : année civile à laquelle appartient le solde.
 *   Un solde d'une année passée vaut 0 (remise à zéro paresseuse + commande).
 * - `points_history.points_revoked` : points déjà retirés sur la commande
 *   (cumul, recalculé depuis la somme des remboursements → idempotent).
 * - `gift_history.year` : un palier est débloquable une fois PAR année civile,
 *   l'unicité passe de (client, palier) à (client, palier, année).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_loyalty_customer_points', function (Blueprint $table): void {
            $table->unsignedSmallInteger('points_year')->nullable()->after('total_points');
        });

        Schema::table('pko_loyalty_points_history', function (Blueprint $table): void {
            $table->unsignedInteger('points_revoked')->default(0)->after('points_earned');
        });

        Schema::table('pko_loyalty_gift_history', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->nullable()->after('tier_id');
        });

        // Soldes existants : rattachés à l'année de leur dernière mise à jour,
        // pour qu'un solde de l'an dernier soit bien remis à zéro.
        DB::table('pko_loyalty_customer_points')->orderBy('id')->each(function (object $row): void {
            DB::table('pko_loyalty_customer_points')->where('id', $row->id)->update([
                'points_year' => (int) date('Y', strtotime((string) ($row->last_order_at ?? $row->updated_at ?? 'now'))),
            ]);
        });

        DB::table('pko_loyalty_gift_history')->orderBy('id')->each(function (object $row): void {
            DB::table('pko_loyalty_gift_history')->where('id', $row->id)->update([
                'year' => (int) date('Y', strtotime((string) $row->unlocked_at)),
            ]);
        });

        Schema::table('pko_loyalty_gift_history', function (Blueprint $table): void {
            // La FK customer_id s'appuie sur l'index unique : on pose le nouvel
            // index (qui commence aussi par customer_id) avant de retirer l'ancien.
            $table->unique(['customer_id', 'tier_id', 'year']);
            $table->dropUnique(['customer_id', 'tier_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pko_loyalty_gift_history', function (Blueprint $table): void {
            $table->unique(['customer_id', 'tier_id']);
            $table->dropUnique(['customer_id', 'tier_id', 'year']);
            $table->dropColumn('year');
        });

        Schema::table('pko_loyalty_points_history', function (Blueprint $table): void {
            $table->dropColumn('points_revoked');
        });

        Schema::table('pko_loyalty_customer_points', function (Blueprint $table): void {
            $table->dropColumn('points_year');
        });
    }
};
