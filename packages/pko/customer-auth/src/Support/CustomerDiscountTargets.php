<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Support;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Models\CustomerDiscount;

/**
 * Libellés et options des cibles de remise client, pour le back-office.
 *
 * - Collections : chemin complet « Famille › Sous-famille › Sous-sous-famille »,
 *   dans l'ordre de l'arbre, groupé par groupe de collections (optgroup).
 * - Familles fabricant : « Marque — CODE — Libellé » (table `pko_brand_families`
 *   lue directement : pas de dépendance de classe vers pko/catalog-features).
 * - Marques Lunar : nom.
 *
 * Lié en `scoped` : chaque liste est chargée une seule fois par requête (pas de
 * N+1 sur les colonnes « Cible » des tables).
 */
class CustomerDiscountTargets
{
    /** @var array<string, array<int, string>> */
    private array $labels = [];

    /** @var array<string, array<int, string>>|null */
    private ?array $groupedCollections = null;

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            CustomerDiscount::TARGET_COLLECTION => 'Catégorie (famille, sous-famille…)',
            CustomerDiscount::TARGET_BRAND_FAMILY => 'Famille fabricant',
            CustomerDiscount::TARGET_BRAND => 'Marque',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            CustomerDiscount::TARGET_COLLECTION => 'Catégorie',
            CustomerDiscount::TARGET_BRAND_FAMILY => 'Famille fabricant',
            CustomerDiscount::TARGET_BRAND => 'Marque',
            default => '—',
        };
    }

    /**
     * Options du select « Cible » pour un type. Collections : groupées par groupe
     * de collections (tableau imbriqué → optgroup Filament).
     *
     * @return array<int|string, string|array<int, string>>
     */
    public function options(?string $type): array
    {
        if ($type === CustomerDiscount::TARGET_COLLECTION) {
            $this->loadCollections();

            return count($this->groupedCollections) === 1
                ? array_values($this->groupedCollections)[0]
                : $this->groupedCollections;
        }

        return $type === null || ! in_array($type, CustomerDiscount::TARGET_TYPES, true)
            ? []
            : $this->labelsFor($type);
    }

    public function label(?string $type, int|string|null $id): ?string
    {
        if ($type === null || $id === null || $id === '') {
            return null;
        }

        return $this->labelsFor($type)[(int) $id] ?? "#{$id} (supprimée)";
    }

    public static function customerLabel(?Customer $customer): string
    {
        if ($customer === null) {
            return '—';
        }

        $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        $company = trim((string) ($customer->company_name ?? ''));

        return match (true) {
            $company !== '' && $name !== '' => "{$company} ({$name})",
            $company !== '' => $company,
            $name !== '' => $name,
            default => "Client #{$customer->id}",
        };
    }

    /** @return array<int, string> */
    private function labelsFor(string $type): array
    {
        if (isset($this->labels[$type])) {
            return $this->labels[$type];
        }

        return $this->labels[$type] = match ($type) {
            CustomerDiscount::TARGET_COLLECTION => $this->collectionLabels(),
            CustomerDiscount::TARGET_BRAND_FAMILY => $this->brandFamilyLabels(),
            CustomerDiscount::TARGET_BRAND => DB::table('lunar_brands')->orderBy('name')->pluck('name', 'id')
                ->map(fn ($name): string => (string) $name)
                ->all(),
            default => [],
        };
    }

    /** @return array<int, string> */
    private function collectionLabels(): array
    {
        $this->loadCollections();

        $flat = [];
        foreach ($this->groupedCollections as $options) {
            $flat += $options;
        }

        return $flat;
    }

    private function loadCollections(): void
    {
        if ($this->groupedCollections !== null) {
            return;
        }

        $collections = LunarCollection::query()
            ->orderBy('collection_group_id')
            ->orderBy('_lft')
            ->get(['id', 'parent_id', 'collection_group_id', 'attribute_data']);

        $names = $collections->mapWithKeys(fn (LunarCollection $c): array => [
            $c->id => (string) ($c->translateAttribute('name') ?: "#{$c->id}"),
        ]);
        $parents = $collections->pluck('parent_id', 'id');
        $groups = CollectionGroup::query()->pluck('name', 'id');

        $grouped = [];
        foreach ($collections as $collection) {
            $trail = [];
            $guard = 0;
            for ($id = $collection->id; $id !== null && isset($names[$id]) && $guard++ < 20; $id = $parents[$id] ?? null) {
                array_unshift($trail, $names[$id]);
            }

            $group = (string) ($groups[$collection->collection_group_id] ?? 'Sans groupe');
            $grouped[$group][(int) $collection->id] = implode(' › ', $trail);
        }

        $this->groupedCollections = $grouped;
    }

    /** @return array<int, string> */
    private function brandFamilyLabels(): array
    {
        return DB::table('pko_brand_families')
            ->leftJoin('lunar_brands', 'lunar_brands.id', '=', 'pko_brand_families.brand_id')
            ->orderBy('lunar_brands.name')
            ->orderBy('pko_brand_families.code')
            ->get(['pko_brand_families.id', 'pko_brand_families.code', 'pko_brand_families.label', 'lunar_brands.name as brand'])
            ->mapWithKeys(function (object $row): array {
                $family = filled($row->label) ? "{$row->code} — {$row->label}" : (string) $row->code;

                return [(int) $row->id => filled($row->brand) ? "{$row->brand} · {$family}" : $family];
            })
            ->all();
    }
}
