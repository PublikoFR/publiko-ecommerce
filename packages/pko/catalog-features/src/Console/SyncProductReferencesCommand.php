<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lunar\Models\ProductVariant;
use Pko\CatalogFeatures\Support\ProductReference;

/**
 * Recalcule le SKU des variantes existantes = préfixe marque + MPN
 * ({@see ProductReference}). Jamais lancé automatiquement.
 *
 * Classement de chaque variante :
 *  - conforme         : SKU déjà égal à la référence composée ;
 *  - à modifier       : SKU différent, cible libre → réécrit (hors --dry-run) ;
 *  - sans préfixe     : produit sans marque, ou marque sans préfixe ;
 *  - MPN vide         : référence fabricant absente ;
 *  - cible déjà prise : la référence composée est le SKU d'une AUTRE variante,
 *                       ou plusieurs variantes composent la même cible
 *                       (même marque + même MPN) — rien n'est touché.
 *
 * Le MPN n'est jamais modifié.
 */
class SyncProductReferencesCommand extends Command
{
    protected $signature = 'pko:references:sync
        {--dry-run : Rapport seul, aucune écriture}
        {--force : Ne demande pas de confirmation avant écriture}
        {--details=20 : Nombre de lignes détaillées par catégorie (0 = aucune)}';

    protected $description = 'Recalcule les SKU (préfixe marque + référence fabricant) des produits existants.';

    private const CHUNK = 1000;

    public function handle(): int
    {
        $prefix = config('lunar.database.table_prefix', 'lunar_');

        /** @var array<int, array{id:int, sku:?string, mpn:?string, target:?string}> $rows */
        $rows = [];
        $report = ['conform' => [], 'update' => [], 'no_prefix' => [], 'no_mpn' => [], 'taken' => []];

        DB::table($prefix.'product_variants as v')
            ->join($prefix.'products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin($prefix.'brands as b', 'b.id', '=', 'p.brand_id')
            ->whereNull('p.deleted_at')
            ->whereNull('v.deleted_at')
            ->select(['v.id', 'v.sku', 'v.mpn', 'b.pko_ref_prefix', 'b.name as brand_name'])
            ->orderBy('v.id')
            ->chunk(self::CHUNK, function ($chunk) use (&$rows, &$report): void {
                foreach ($chunk as $row) {
                    $sku = $row->sku === null ? null : (string) $row->sku;
                    $mpn = $row->mpn === null ? null : (string) $row->mpn;
                    $line = ['id' => (int) $row->id, 'sku' => $sku, 'mpn' => $mpn, 'brand' => $row->brand_name, 'target' => null];

                    if ($row->pko_ref_prefix === null || $row->pko_ref_prefix === '') {
                        $report['no_prefix'][] = $line;

                        continue;
                    }
                    $target = ProductReference::composeFromPrefix((string) $row->pko_ref_prefix, $mpn);
                    if ($target === null) {
                        $report['no_mpn'][] = $line;

                        continue;
                    }
                    $line['target'] = $target;
                    $rows[] = $line;
                }
            });

        // Cibles composées par plusieurs variantes → ambiguës. Clés en minuscules :
        // la collation MySQL de `sku` est insensible à la casse.
        $targetCounts = array_count_values(array_map(fn (array $r): string => mb_strtolower($r['target']), $rows));
        $takenBy = $this->existingSkuOwners(array_map('strval', array_keys($targetCounts)));

        foreach ($rows as $line) {
            $target = $line['target'];
            if ($line['sku'] === $target) {
                $report['conform'][] = $line;

                continue;
            }
            $key = mb_strtolower($target);
            $owners = array_diff($takenBy[$key] ?? [], [$line['id']]);
            if ($targetCounts[$key] > 1 || $owners !== []) {
                $report['taken'][] = $line;

                continue;
            }
            $report['update'][] = $line;
        }

        $this->printReport($report);

        if ($this->option('dry-run')) {
            $this->line('Dry-run : aucune écriture.');

            return self::SUCCESS;
        }

        if ($report['update'] === []) {
            $this->info('Rien à modifier.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(count($report['update']).' SKU vont être réécrits. Continuer ?')) {
            $this->line('Annulé.');

            return self::SUCCESS;
        }

        $written = 0;
        foreach (array_chunk($report['update'], self::CHUNK) as $chunk) {
            DB::transaction(function () use ($chunk, &$written): void {
                foreach ($chunk as $line) {
                    // Assignation sur le modèle : déclenche observers / réindexation Scout.
                    $variant = ProductVariant::query()->find($line['id']);
                    if ($variant === null) {
                        continue;
                    }
                    $variant->sku = $line['target'];
                    $variant->save();
                    $written++;
                }
            });
        }

        $this->info("{$written} SKU réécrit(s).");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, array<int, int>> sku (minuscules) → ids des variantes qui le portent
     */
    private function existingSkuOwners(array $skus): array
    {
        $owners = [];
        foreach (array_chunk($skus, self::CHUNK) as $chunk) {
            ProductVariant::query()
                ->whereIn('sku', $chunk)
                ->get(['id', 'sku'])
                ->each(function (ProductVariant $v) use (&$owners): void {
                    $owners[mb_strtolower((string) $v->sku)][] = (int) $v->id;
                });
        }

        return $owners;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $report
     */
    private function printReport(array $report): void
    {
        $labels = [
            'update' => 'À modifier',
            'conform' => 'Déjà conformes',
            'no_prefix' => 'Marque absente ou sans préfixe',
            'no_mpn' => 'MPN vide',
            'taken' => 'SKU cible déjà pris / ambigu',
        ];

        $this->table(['Catégorie', 'Variantes'], array_map(
            fn (string $key): array => [$labels[$key], count($report[$key])],
            array_keys($labels),
        ));

        $max = max(0, (int) $this->option('details'));
        if ($max === 0) {
            return;
        }

        foreach (['update', 'taken', 'no_prefix', 'no_mpn'] as $key) {
            if ($report[$key] === []) {
                continue;
            }
            $this->line($labels[$key].' :');
            $this->table(['Variante', 'Marque', 'SKU actuel', 'MPN', 'SKU cible'], array_map(
                fn (array $l): array => [$l['id'], $l['brand'] ?? '—', $l['sku'] ?? '—', $l['mpn'] ?? '—', $l['target'] ?? '—'],
                array_slice($report[$key], 0, $max),
            ));
            if (count($report[$key]) > $max) {
                $this->line('… '.(count($report[$key]) - $max).' de plus.');
            }
        }
    }
}
