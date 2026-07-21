<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Politique de visibilité du catalogue par groupe client.
 *
 * ── Sémantique maison : absence de ligne = visible ──────────────────────────
 *
 * Lunar traite `lunar_collection_customer_group` et `lunar_customer_group_product`
 * en opt-in : le trait `HasCustomerGroups` sème, à la création de CHAQUE
 * collection / produit, une ligne pour TOUS les groupes clients existants, à
 * `enabled = visible = $group->default` — donc à false pour tout groupe non
 * défaut (cf. vendor/lunarphp/core/src/Base/Traits/HasCustomerGroups.php:29).
 *
 * Trois problèmes :
 *   1. le semis est unidirectionnel — un groupe créé APRÈS l'import du catalogue
 *      n'a aucune ligne, et le scope Lunar (`whereHas` + `enabled OR visible`)
 *      lui masquerait alors la totalité du catalogue, silencieusement ;
 *   2. des centaines de milliers de lignes ne portant aucune décision humaine ;
 *   3. ces lignes fantômes faisaient échouer la suppression d'un groupe client
 *      (cf. CustomerGroupGuard), un groupe neuf étant déjà « référencé » 494 fois.
 *
 * On inverse donc en opt-out : **pas de ligne = autorisé**. Une ligne n'est créée
 * que pour restreindre, et seules ces lignes-là comptent.
 *
 * Conséquence : le scope Lunar `customerGroup()` suppose l'inverse et ne doit
 * PAS être utilisé tel quel. Le storefront ne filtre pas par groupe client (choix
 * produit : tous les groupes voient tout, seuls les tarifs diffèrent), donc la
 * question ne se pose pas aujourd'hui. Cf. docs/admin.md.
 */
class CatalogAvailability
{
    /**
     * Pivots de visibilité catalogue auto-semés par Lunar.
     *
     * @var array<string, array{fk: string, parent: string, flags: array<int, string>}>
     */
    public const PIVOTS = [
        'lunar_collection_customer_group' => [
            'fk' => 'collection_id',
            'parent' => 'lunar_collections',
            'flags' => ['enabled', 'visible'],
        ],
        'lunar_customer_group_product' => [
            'fk' => 'product_id',
            'parent' => 'lunar_products',
            // `purchasable` est semé par Product::getExtraCustomerGroupPivotValues(),
            // à la même valeur que les deux autres.
            'flags' => ['enabled', 'visible', 'purchasable'],
        ],
    ];

    /**
     * Tolérance, en secondes, entre l'horodatage du pivot et celui de son parent.
     *
     * Le semis a lieu dans le `created` du modèle, donc dans la même seconde que
     * sa création. On tolère une marge pour les imports lents.
     */
    private const SEED_TOLERANCE_SECONDS = 5;

    /**
     * Cible les seules lignes portant la SIGNATURE du semis automatique.
     *
     * On ne purge pas la table : une ligne saisie à la main est une décision
     * métier et doit survivre. Une ligne est réputée semée si — et seulement si —
     * elle réunit tous les marqueurs suivants :
     *
     *   1. tous ses flags valent exactement `customer_group.default` — c'est ce
     *      qu'écrit le trait (`'enabled' => $customerGroup->default`, etc.) ;
     *   2. `ends_at` est NULL — le trait ne pose jamais de date de fin ;
     *   3. `starts_at` ET `created_at` tombent dans la même seconde que la
     *      création du parent — le semis est déclenché par le `created` du
     *      modèle, alors qu'une saisie humaine intervient nécessairement plus tard.
     *
     * Le point 3 est le plus discriminant : il distingue une ligne « tous flags à
     * false » semée à l'import d'une restriction identique posée volontairement.
     *
     * Vérifié sur la base de développement : les 3 458 lignes de collections et
     * les 100 lignes de produits réunissent les trois marqueurs, aucune ligne
     * saisie à la main n'existe.
     */
    private static function seededRows(string $table): Builder
    {
        $config = self::PIVOTS[$table];
        $parent = $config['parent'];
        $tolerance = self::SEED_TOLERANCE_SECONDS;

        $query = DB::table($table)
            ->join($parent, "{$parent}.id", '=', "{$table}.{$config['fk']}")
            ->join('lunar_customer_groups', 'lunar_customer_groups.id', '=', "{$table}.customer_group_id")
            ->whereNull("{$table}.ends_at")
            ->whereRaw("ABS(TIMESTAMPDIFF(SECOND, {$table}.created_at, {$parent}.created_at)) <= ?", [$tolerance])
            ->whereRaw("ABS(TIMESTAMPDIFF(SECOND, {$table}.starts_at, {$parent}.created_at)) <= ?", [$tolerance]);

        foreach ($config['flags'] as $flag) {
            $query->whereRaw("{$table}.{$flag} = lunar_customer_groups.`default`");
        }

        return $query;
    }

    /**
     * Compte les lignes semées automatiquement, sans rien supprimer.
     *
     * @return array<string, array{seeded: int, total: int}>
     */
    public static function auditSeededRows(): array
    {
        $audit = [];

        foreach (array_keys(self::PIVOTS) as $table) {
            $audit[$table] = [
                'seeded' => self::seededRows($table)->count(),
                'total' => DB::table($table)->count(),
            ];
        }

        return $audit;
    }

    /**
     * Supprime les seules lignes semées automatiquement. Idempotent.
     *
     * @return array<string, int> table => lignes supprimées
     */
    public static function deleteSeededRows(): array
    {
        $deleted = [];

        foreach (array_keys(self::PIVOTS) as $table) {
            // La suppression passe par les ids : un DELETE avec JOIN n'est pas
            // portable via le query builder, et on veut relire exactement ce que
            // le comptage a montré.
            $ids = self::seededRows($table)->pluck("{$table}.id");

            $deleted[$table] = $ids->isEmpty()
                ? 0
                : DB::table($table)->whereIn('id', $ids)->delete();
        }

        return $deleted;
    }

    /**
     * Supprime les lignes que le trait Lunar vient de semer pour un enregistrement
     * fraîchement créé. Appelé depuis l'observer `created` — voir
     * CatalogAvailabilityObserver pour la contrainte d'ordre des listeners.
     */
    public static function forget(string $table, int $recordId): int
    {
        $fk = self::PIVOTS[$table]['fk'];

        return DB::table($table)->where($fk, $recordId)->delete();
    }

    /**
     * Nombre de lignes RESTRICTIVES d'un groupe client sur un pivot donné.
     *
     * Une ligne restreint dès qu'au moins un flag de disponibilité est à 0. Une
     * ligne dont tous les flags sont à 1 est redondante avec le défaut implicite
     * (« pas de ligne = visible ») et ne bloque donc rien.
     */
    public static function restrictionCount(string $table, int $customerGroupId): int
    {
        $flags = self::PIVOTS[$table]['flags'];

        return DB::table($table)
            ->where('customer_group_id', $customerGroupId)
            ->where(function ($query) use ($flags): void {
                foreach ($flags as $flag) {
                    $query->orWhere($flag, '=', false);
                }
            })
            ->count();
    }
}
