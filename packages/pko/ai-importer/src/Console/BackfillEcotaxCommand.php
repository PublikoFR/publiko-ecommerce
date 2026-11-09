<?php

declare(strict_types=1);

namespace Pko\AiImporter\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lunar\Models\Currency;
use Lunar\Models\ProductVariant;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Support\NumberParser;

/**
 * Rattrapage de l'éco-participation des produits importés AVANT son support.
 *
 * PrestaShop fournit `price_tex` HORS éco-taxe : les produits déjà importés ont
 * un prix sans éco-taxe et `pko_ecotax_cents` NULL. Pour chaque produit, on lit
 * la ligne de staging la plus récente dont `data.ecotax` > 0, puis, pour chaque
 * variante dont `pko_ecotax_cents` est NULL :
 *   - pose `pko_ecotax_cents` ;
 *   - AJOUTE l'éco-taxe à tous les paliers `lunar_prices` de la variante dans la
 *     devise par défaut (modèle « dont » : le prix inclut l'éco-taxe).
 *
 * Idempotente : une variante dont `pko_ecotax_cents` n'est plus NULL est ignorée.
 * Les prix négociés (`pko_negotiated_prices`) ne sont PAS modifiés : ils sont
 * listés pour arbitrage manuel.
 *
 *   make artisan CMD='ai-importer:backfill-ecotax --dry-run'
 */
class BackfillEcotaxCommand extends Command
{
    protected $signature = 'ai-importer:backfill-ecotax
                            {--dry-run : Affiche les changements sans rien écrire}';

    protected $description = 'Rattrape l\'éco-participation (pko_ecotax_cents + ajout au prix) depuis le staging PrestaShop.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $currency = Currency::query()->where('default', true)->first() ?? Currency::query()->orderBy('id')->first();
        if ($currency === null) {
            $this->error('Aucune devise configurée.');

            return self::FAILURE;
        }

        $ecotaxByProduct = $this->latestEcotaxByProduct();
        if ($ecotaxByProduct === []) {
            $this->info('Aucune ligne de staging avec une éco-taxe > 0.');

            return self::SUCCESS;
        }

        $variants = ProductVariant::query()
            ->whereIn('product_id', array_keys($ecotaxByProduct))
            ->whereNull('pko_ecotax_cents')
            ->orderBy('id')
            ->get();

        if ($variants->isEmpty()) {
            $this->info('Rien à rattraper : toutes les variantes concernées ont déjà une éco-taxe.');

            return self::SUCCESS;
        }

        $priceableTypes = [(new ProductVariant)->getMorphClass(), ProductVariant::class];
        $rows = [];

        foreach ($variants as $variant) {
            $ecotax = $ecotaxByProduct[(int) $variant->product_id];
            $pricesQuery = fn () => DB::table('lunar_prices')
                ->whereIn('priceable_type', array_unique($priceableTypes))
                ->where('priceable_id', $variant->id)
                ->where('currency_id', $currency->id);

            $oldBase = $pricesQuery()
                ->whereNull('customer_group_id')
                ->orderBy('min_quantity')
                ->value('price');

            $rows[] = [
                $variant->sku,
                $this->formatCents($oldBase !== null ? (int) $oldBase : null),
                $this->formatCents($ecotax),
                $this->formatCents($oldBase !== null ? (int) $oldBase + $ecotax : null),
            ];

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($variant, $ecotax, $pricesQuery): void {
                $pricesQuery()->increment('price', $ecotax);

                // Assignation directe : colonne custom non-fillable.
                $variant->pko_ecotax_cents = $ecotax;
                $variant->save();
            });
        }

        $this->table(['SKU', 'Ancien prix HT', 'Éco-participation', 'Nouveau prix HT'], $rows);

        $this->reportNegotiatedPrices($variants->pluck('sku', 'id')->all(), (int) $currency->id);

        $this->info(sprintf(
            '%d variante(s) %s.',
            count($rows),
            $dryRun ? 'à rattraper (dry-run : aucune écriture)' : 'rattrapée(s)',
        ));

        return self::SUCCESS;
    }

    /**
     * Éco-taxe (cents) de la ligne de staging la plus récente par produit,
     * parmi celles dont `data.ecotax` > 0.
     *
     * @return array<int, int> lunar_product_id → cents
     */
    private function latestEcotaxByProduct(): array
    {
        $result = [];

        StagingRecord::query()
            ->whereNotNull('lunar_product_id')
            ->lazyByIdDesc(200, 'id')
            ->each(function (StagingRecord $record) use (&$result): void {
                $productId = (int) $record->lunar_product_id;
                if (isset($result[$productId])) {
                    return;
                }

                $data = $record->data instanceof \ArrayObject ? $record->data->getArrayCopy() : (array) $record->data;
                $cents = NumberParser::isBlank($data['ecotax'] ?? null) ? null : NumberParser::eurosToCents($data['ecotax']);

                if ($cents !== null && $cents > 0) {
                    $result[$productId] = $cents;
                }
            });

        return $result;
    }

    /**
     * Liste (sans les modifier) les prix négociés des variantes concernées.
     *
     * @param  array<int, string>  $skuByVariant
     */
    private function reportNegotiatedPrices(array $skuByVariant, int $currencyId): void
    {
        if (! Schema::hasTable('pko_negotiated_prices')) {
            return;
        }

        $negotiated = DB::table('pko_negotiated_prices')
            ->whereIn('product_variant_id', array_keys($skuByVariant))
            ->where('currency_id', $currencyId)
            ->orderBy('product_variant_id')
            ->get(['customer_id', 'product_variant_id', 'price']);

        if ($negotiated->isEmpty()) {
            return;
        }

        $this->warn('Prix négociés NON modifiés (à arbitrer manuellement) :');
        $this->table(
            ['SKU', 'Client (id)', 'Prix négocié HT'],
            $negotiated->map(fn (object $row): array => [
                $skuByVariant[(int) $row->product_variant_id] ?? (string) $row->product_variant_id,
                (string) $row->customer_id,
                $this->formatCents((int) $row->price),
            ])->all(),
        );
    }

    private function formatCents(?int $cents): string
    {
        return $cents === null ? '—' : number_format($cents / 100, 2, ',', ' ').' €';
    }
}
