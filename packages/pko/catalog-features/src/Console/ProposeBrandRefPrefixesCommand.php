<?php

declare(strict_types=1);

namespace Pko\CatalogFeatures\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lunar\Models\Brand;
use Pko\CatalogFeatures\Support\BrandReferencePrefix;

/**
 * Propose un préfixe de référence aux marques qui n'en ont pas.
 *
 * Sans `--apply` : rapport seul. Avec `--apply` : pose uniquement les
 * propositions SANS collision sur les marques sans préfixe. Ne réécrit jamais
 * un préfixe existant. Les collisions (préfixe déjà pris, ou proposé à
 * plusieurs marques) sont listées avec des alternatives : l'admin arbitre.
 */
class ProposeBrandRefPrefixesCommand extends Command
{
    protected $signature = 'pko:brands:ref-prefixes {--apply : Enregistre les propositions sans collision}';

    protected $description = 'Propose un préfixe de référence aux marques sans préfixe et liste les collisions.';

    public function handle(): int
    {
        /** @var Collection<int, Brand> $brands */
        $brands = Brand::query()->orderBy('name')->get(['id', 'name', 'pko_ref_prefix']);
        $taken = $brands->pluck('pko_ref_prefix')->filter()->flip();
        $pending = $brands->filter(fn (Brand $b): bool => blank($b->pko_ref_prefix));

        $proposals = [];
        $invalid = [];
        foreach ($pending as $brand) {
            $prefix = BrandReferencePrefix::suggest($brand->name);
            if ($prefix === null) {
                $invalid[] = $brand;

                continue;
            }
            $proposals[$prefix][] = $brand;
        }

        $ok = [];
        $collisions = [];
        foreach ($proposals as $prefix => $group) {
            if (count($group) === 1 && ! isset($taken[$prefix])) {
                $ok[] = [$group[0], $prefix];

                continue;
            }
            $owner = $brands->firstWhere('pko_ref_prefix', $prefix);
            foreach ($group as $brand) {
                $others = collect($group)->reject(fn (Brand $b): bool => $b->is($brand))->pluck('name');
                $conflict = $owner !== null
                    ? "déjà pris par « {$owner->name} »"
                    : 'proposé aussi à « '.$others->implode(' », « ').' »';
                $collisions[] = [
                    $brand->id,
                    $brand->name,
                    $prefix,
                    $conflict,
                    implode(', ', BrandReferencePrefix::alternatives($brand->name, $brand->id, 3, [$prefix])),
                ];
            }
        }

        $this->info(sprintf(
            '%d marque(s) déjà préfixée(s), %d proposition(s) sans collision, %d collision(s), %d nom(s) sans proposition possible.',
            $brands->count() - $pending->count(),
            count($ok),
            count($collisions),
            count($invalid),
        ));

        if ($ok !== []) {
            $this->table(['ID', 'Marque', 'Préfixe proposé'], array_map(
                fn (array $row): array => [$row[0]->id, $row[0]->name, $row[1]],
                $ok,
            ));
        }

        if ($collisions !== []) {
            $this->warn('Collisions — à arbitrer dans l\'admin (Marques → Préfixe de référence) :');
            $this->table(['ID', 'Marque', 'Préfixe', 'Conflit', 'Alternatives'], $collisions);
        }

        foreach ($invalid as $brand) {
            $this->warn("« {$brand->name} » (#{$brand->id}) : moins de 2 lettres, préfixe à saisir à la main.");
        }

        if (! $this->option('apply')) {
            $this->line('Rapport seul : relancer avec --apply pour enregistrer les propositions sans collision.');

            return self::SUCCESS;
        }

        $written = 0;
        foreach ($ok as [$brand, $prefix]) {
            // Garde : on ne remplit qu'une marque toujours sans préfixe.
            $written += Brand::query()
                ->whereKey($brand->id)
                ->whereNull('pko_ref_prefix')
                ->update(['pko_ref_prefix' => $prefix]);
        }
        $this->info("{$written} préfixe(s) enregistré(s).");

        return self::SUCCESS;
    }
}
