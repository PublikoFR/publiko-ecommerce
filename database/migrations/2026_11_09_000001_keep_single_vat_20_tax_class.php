<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Une seule classe de TVA : 20 % (FEAT-003, décision du 2026-11-09).
 *
 * La boutique ne vend qu'en TVA 20 %. `lunar:install` crée une « Default Tax
 * Class » sans aucun taux, puis `PkoTaxSeeder` ajoutait 20 / 10 / 5,5 %.
 * L'importeur retombait sur la « première » classe (id le plus bas) : des
 * variantes se sont retrouvées sur la classe sans taux, donc vendues sans TVA.
 *
 * La classe gardée est identifiée par ses ATTRIBUTS, jamais par id (les ids
 * diffèrent entre dev et prod) : une classe portant un taux à 20 %, de
 * préférence celle marquée `default`, sinon la plus ancienne. Toutes les
 * références y sont réaffectées avant suppression des autres classes — les
 * seules FK vers `lunar_tax_classes` sont `lunar_product_variants.tax_class_id`
 * et `lunar_tax_rate_amounts.tax_class_id`. Les `order_lines` historiques n'ont
 * pas de classe (elles figent `tax_breakdown`) : intactes.
 *
 * Idempotente : rejouée sur une base déjà à une classe, elle ne fait que
 * réaffirmer `default = 1`. Sans classe à 20 % (base neuve, tests), elle ne
 * supprime rien.
 */
return new class extends Migration
{
    private const VAT_PERCENTAGE = 20;

    public function up(): void
    {
        if (! Schema::hasTable('lunar_tax_classes') || ! Schema::hasTable('lunar_tax_rate_amounts')) {
            return;
        }

        $keepId = $this->resolveKeptClassId();
        if ($keepId === null) {
            if (DB::table('lunar_tax_classes')->count() > 1) {
                Log::warning('[tax] Aucune classe de taxe à 20 % : classes laissées en l\'état.');
            }

            return;
        }

        DB::transaction(function () use ($keepId): void {
            DB::table('lunar_product_variants')
                ->where('tax_class_id', '!=', $keepId)
                ->update(['tax_class_id' => $keepId]);

            $obsoleteAmounts = DB::table('lunar_tax_rate_amounts')
                ->whereNotNull('tax_class_id')
                ->where('tax_class_id', '!=', $keepId);

            $touchedRateIds = (clone $obsoleteAmounts)->pluck('tax_rate_id')->filter()->unique()->values();
            $obsoleteAmounts->delete();

            // Un taux (« Réduit 10 % ») vidé de tous ses montants ne sert plus à rien.
            if ($touchedRateIds->isNotEmpty() && Schema::hasTable('lunar_tax_rates')) {
                $stillUsed = DB::table('lunar_tax_rate_amounts')
                    ->whereIn('tax_rate_id', $touchedRateIds)
                    ->pluck('tax_rate_id');

                DB::table('lunar_tax_rates')
                    ->whereIn('id', $touchedRateIds->diff($stillUsed))
                    ->delete();
            }

            DB::table('lunar_tax_classes')->where('id', '!=', $keepId)->delete();
            DB::table('lunar_tax_classes')->where('id', $keepId)->update(['default' => true]);
        });
    }

    /**
     * Suppression de données : pas de retour arrière automatique.
     */
    public function down(): void {}

    private function resolveKeptClassId(): ?int
    {
        $id = DB::table('lunar_tax_classes as c')
            ->join('lunar_tax_rate_amounts as a', 'a.tax_class_id', '=', 'c.id')
            ->where('a.percentage', self::VAT_PERCENTAGE)
            ->orderByDesc('c.default')
            ->orderBy('c.id')
            ->value('c.id');

        return $id !== null ? (int) $id : null;
    }
};
