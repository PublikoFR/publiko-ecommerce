<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Collection;
use Pko\Account\Support\OrderStatusLabel;

/**
 * Agrège les statistiques réelles du tableau de bord Weklo depuis les tables
 * Lunar (commandes, lignes, clients, variantes, paniers, remises, fidélité).
 *
 * Trois métriques n'ont AUCUNE source réelle dans le schéma (pas d'analytics
 * web ni de prix d'achat) : taux de conversion, étapes hautes du tunnel
 * (sessions / visiteurs / ajouts panier) et marge brute. Elles sont générées
 * de façon pseudo-aléatoire (stable par période) et marquées `simulated => true`
 * pour afficher un badge « stat à connecter » côté UI.
 */
final class DashboardStats
{
    /** Périodes supportées : clé => [libellé, libellé précédent, nb buckets, granularité]. */
    private const PERIODS = ['jour', '7j', '30j', '12m'];

    /**
     * Mappe un statut de commande Lunar (configurable) vers l'un des 4 buckets
     * du donut design. Tout statut inconnu retombe sur « pending ».
     */
    private const STATUS_BUCKET = [
        'awaiting-payment' => 'pending',
        'awaiting-quote' => 'pending',
        'payment-offline' => 'pending',
        'payment-received' => 'paid',
        'dispatched' => 'paid',
        'delivered' => 'paid',
        'in-preparation' => 'toship',
        'awaiting-fulfilment' => 'toship',
        'cancelled' => 'refunded',
        'refunded' => 'refunded',
        'payment-refunded' => 'refunded',
    ];

    /**
     * Construit les stats pour une plage de dates personnalisée (format YYYY-MM-DD).
     *
     * @return array<string, mixed>
     */
    public function buildCustom(string $startStr, string $endStr): array
    {
        $start = CarbonImmutable::parse($startStr)->startOfDay();
        $end = CarbonImmutable::parse($endStr)->endOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        $prevEnd = $start->subSecond();
        $prevStart = $prevEnd->subDays($days - 1)->startOfDay();

        $rng = $this->seededRng(crc32($startStr.$endStr));
        $fmt = fn (CarbonImmutable $d): string => $d->locale('fr')->isoFormat('D MMM YYYY');
        $dateLabel = $start->isSameDay($end) ? $fmt($start) : $fmt($start).' – '.$fmt($end);

        return [
            'period' => 'custom',
            'periodLabel' => $dateLabel,
            'prevLabel' => 'période préc.',
            'dateRange' => $dateLabel,
            'compare' => true,
            'kpis' => $this->kpis($start, $end, $prevStart, $prevEnd, $rng),
            'miniStats' => $this->miniStats($start, $end),
            'caChart' => $this->caChartCustom($start, $end, $prevStart, $days),
            'status' => $this->statusBreakdown($start, $end),
            'bestSellers' => $this->bestSellers(),
            'categories' => $this->categories(),
            'regions' => $this->regions(),
            'clientTypes' => $this->clientTypes(),
            'funnel' => $this->funnel($start, $end, $rng),
            'stock' => $this->stock(),
            'ruptureCount' => $this->ruptureCount(),
            'devis' => $this->devis(),
            'devisTotal' => $this->eur($this->devisPotential()),
            'promos' => $this->promos($rng),
            'orders' => $this->recentOrders(),
        ];
    }

    public function build(string $period, bool $compare = true): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : '30j';
        [$start, $end] = $this->range($period);
        [$prevStart, $prevEnd] = $this->previousRange($period, $start);

        $rng = $this->seededRng(crc32($period));

        return [
            'period' => $period,
            'periodLabel' => $this->periodLabel($period),
            'prevLabel' => $this->prevLabel($period),
            'dateRange' => $this->dateRangeLabel($start, $end),
            'compare' => $compare,
            'kpis' => $this->kpis($start, $end, $prevStart, $prevEnd, $rng),
            'miniStats' => $this->miniStats($start, $end),
            'caChart' => $this->caChart($period, $start, $end, $prevStart),
            'status' => $this->statusBreakdown($start, $end),
            'bestSellers' => $this->bestSellers(),
            'categories' => $this->categories(),
            'regions' => $this->regions(),
            'clientTypes' => $this->clientTypes(),
            'funnel' => $this->funnel($start, $end, $rng),
            'stock' => $this->stock(),
            'ruptureCount' => $this->ruptureCount(),
            'devis' => $this->devis(),
            'devisTotal' => $this->eur($this->devisPotential()),
            'promos' => $this->promos($rng),
            'orders' => $this->recentOrders(),
        ];
    }

    // ---------------------------------------------------------------- périodes

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'jour' => [$now->startOfDay(), $now->endOfDay()],
            '7j' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '12m' => [$now->subMonths(11)->startOfMonth(), $now->endOfMonth()],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function previousRange(string $period, CarbonImmutable $start): array
    {
        return match ($period) {
            'jour' => [$start->subDay(), $start->subSecond()],
            '7j' => [$start->subDays(7), $start->subSecond()],
            '12m' => [$start->subMonths(12), $start->subSecond()],
            default => [$start->subDays(30), $start->subSecond()],
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'jour' => "aujourd'hui",
            '7j' => '7 derniers jours',
            '12m' => '12 derniers mois',
            default => '30 derniers jours',
        };
    }

    private function prevLabel(string $period): string
    {
        return match ($period) {
            'jour' => 'hier',
            '7j' => '7 j. préc.',
            '12m' => '12 mois préc.',
            default => '30 j. préc.',
        };
    }

    private function dateRangeLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        $fmt = fn (CarbonImmutable $d) => $d->locale('fr')->isoFormat('D MMM YYYY');

        return $start->isSameDay($end) ? $fmt($start) : $fmt($start).' – '.$fmt($end);
    }

    // -------------------------------------------------------------------- KPIs

    private function kpis(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $ps, CarbonImmutable $pe, \Closure $rng): array
    {
        $cur = $this->ordersAgg($start, $end);
        $prev = $this->ordersAgg($ps, $pe);

        $aov = $cur->orders > 0 ? $cur->revenue / $cur->orders : 0;
        $aovPrev = $prev->orders > 0 ? $prev->revenue / $prev->orders : 0;

        // Taux de conversion : aucune source analytics → simulé + badge.
        $conv = 2.4 + $rng() * 2.6;
        $convPrev = $conv - (0.2 + $rng() * 0.6);

        return [
            [
                'key' => 'ca', 'label' => "Chiffre d'affaires", 'icon' => 'ca',
                'value' => $this->eur($cur->revenue),
                'spark' => $this->sparkFrom($this->dailySeries($start, $end)),
            ] + $this->delta($cur->revenue, $prev->revenue),
            [
                'key' => 'orders', 'label' => 'Commandes', 'icon' => 'orders',
                'value' => number_format((float) $cur->orders, 0, ',', ' '),
                'spark' => $this->sparkFrom($this->dailySeries($start, $end, 'count')),
            ] + $this->delta($cur->orders, $prev->orders),
            [
                'key' => 'aov', 'label' => 'Panier moyen', 'icon' => 'aov',
                'value' => $this->eur2($aov),
                'spark' => $this->sparkFrom($this->dailySeries($start, $end, 'aov')),
            ] + $this->delta($aov, $aovPrev),
            [
                'key' => 'conv', 'label' => 'Taux de conversion', 'icon' => 'conv',
                'value' => number_format($conv, 1, ',', ' ').' %',
                'simulated' => true,
                'spark' => $this->sparkFrom(array_map(fn () => $rng(), range(1, 16))),
            ] + $this->delta($conv, $convPrev, ' pt', false),
        ];
    }

    /** Agrège CA (sub_total HT, cents) + nb commandes sur une plage. */
    private function ordersAgg(CarbonImmutable $start, CarbonImmutable $end): object
    {
        $row = $this->ordersBase()
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
            ->selectRaw('COALESCE(SUM(sub_total),0) revenue, COUNT(*) orders')
            ->first();

        return (object) ['revenue' => (int) ($row->revenue ?? 0), 'orders' => (int) ($row->orders ?? 0)];
    }

    /** Requête de base : commandes réellement passées (exclut paniers en cours). */
    private function ordersBase()
    {
        return DB::table('lunar_orders')->whereNotNull('placed_at');
    }

    private function delta(float|int $cur, float|int $prev, string $unit = ' %', bool $isPercent = true): array
    {
        if ($isPercent) {
            $d = $prev > 0 ? ($cur - $prev) / $prev * 100 : ($cur > 0 ? 100.0 : 0.0);
        } else {
            $d = $cur - $prev; // écart en points
        }
        $up = $d >= 0;

        return [
            'delta' => ($up ? '+' : '−').number_format(abs($d), 1, ',', ' ').$unit,
            'up' => $up,
        ];
    }

    // ------------------------------------------------------------- mini stats

    private function miniStats(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $newCustomers = DB::table('lunar_customers')->whereBetween('created_at', [$start, $end])->count();
        $devisCount = $this->ordersDevisQuery()->count();
        $abandoned = DB::table('lunar_carts')
            ->whereNull('order_id')->whereNull('completed_at')->whereNull('deleted_at')
            ->whereExists(fn ($q) => $q->from('lunar_cart_lines')->whereColumn('lunar_cart_lines.cart_id', 'lunar_carts.id'))
            ->count();
        $rupture = $this->ruptureCount();

        return [
            [
                'key' => 'newc', 'icon' => 'user-plus', 'tone' => 'brand',
                'value' => number_format((float) $newCustomers, 0, ',', ' '),
                'label' => 'Nouveaux clients', 'sub' => 'sur la période', 'subTone' => 'success',
            ],
            [
                'key' => 'devis', 'icon' => 'file', 'tone' => 'accent',
                'value' => number_format((float) $devisCount, 0, ',', ' '),
                'label' => 'Devis en cours', 'sub' => $this->eur($this->devisPotential()).' potentiel', 'subTone' => 'brand',
            ],
            [
                'key' => 'aband', 'icon' => 'cart', 'tone' => 'warning',
                'value' => number_format((float) $abandoned, 0, ',', ' '),
                'label' => 'Paniers abandonnés', 'sub' => 'non convertis', 'subTone' => 'warning',
            ],
            [
                'key' => 'stock', 'icon' => 'alert', 'tone' => 'danger',
                'value' => number_format((float) $rupture, 0, ',', ' '),
                'label' => 'Produits en rupture', 'sub' => 'stock ≤ 0', 'subTone' => 'danger',
            ],
        ];
    }

    // ------------------------------------------------------------- graphe CA

    private function caChart(string $period, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $prevStart): array
    {
        [$cats, $cur] = $this->bucketSeries($period, $start, $end);
        [, $prev] = $this->bucketSeries($period, $prevStart, $this->previousRange($period, $start)[1]);

        return [
            'categories' => $cats,
            'current' => $cur,
            'previous' => $prev,
        ];
    }

    private function caChartCustom(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $prevStart, int $days): array
    {
        $prevEnd = $prevStart->addDays($days - 1)->endOfDay();
        [$cats, $cur] = $this->bucketSeriesCustom($start, $end, $days);
        [, $prev] = $this->bucketSeriesCustom($prevStart, $prevEnd, $days);

        return ['categories' => $cats, 'current' => $cur, 'previous' => $prev];
    }

    /**
     * Série CA pour une plage custom : horaire (1 j), journalier (≤ 90 j), mensuel (> 90 j).
     *
     * @return array{0: array<int,string>, 1: array<int,float>}
     */
    private function bucketSeriesCustom(CarbonImmutable $start, CarbonImmutable $end, int $days): array
    {
        $cats = [];
        $data = [];

        if ($days === 1) {
            $rows = $this->ordersBase()
                ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
                ->selectRaw('HOUR(COALESCE(placed_at, created_at)) b, SUM(sub_total) v')
                ->groupBy('b')->pluck('v', 'b');
            for ($h = 0; $h < 24; $h++) {
                $cats[] = $h.'h';
                $data[] = round(((int) ($rows[$h] ?? 0)) / 100, 2);
            }

            return [$cats, $data];
        }

        if ($days > 90) {
            $rows = $this->ordersBase()
                ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
                ->selectRaw("DATE_FORMAT(COALESCE(placed_at, created_at), '%Y-%m') b, SUM(sub_total) v")
                ->groupBy('b')->pluck('v', 'b');
            $cursor = $start->startOfMonth();
            while ($cursor->startOfMonth() <= $end->startOfMonth()) {
                $key = $cursor->format('Y-m');
                $cats[] = $cursor->locale('fr')->isoFormat('MMM YY');
                $data[] = round(((int) ($rows[$key] ?? 0)) / 100, 2);
                $cursor = $cursor->addMonth();
            }

            return [$cats, $data];
        }

        // Journalier
        $rows = $this->ordersBase()
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
            ->selectRaw('DATE(COALESCE(placed_at, created_at)) b, SUM(sub_total) v')
            ->groupBy('b')->pluck('v', 'b');
        $cursor = $start;
        while ($cursor->startOfDay() <= $end->startOfDay()) {
            $key = $cursor->format('Y-m-d');
            $cats[] = $days <= 14
                ? $cursor->locale('fr')->isoFormat('D MMM')
                : (string) $cursor->day;
            $data[] = round(((int) ($rows[$key] ?? 0)) / 100, 2);
            $cursor = $cursor->addDay();
        }

        return [$cats, $data];
    }

    /**
     * Série de CA (euros) découpée en buckets selon la période.
     *
     * @return array{0: array<int,string>, 1: array<int,float>}
     */
    private function bucketSeries(string $period, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $cats = [];
        $data = [];

        if ($period === 'jour') {
            $rows = $this->ordersBase()
                ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
                ->selectRaw('HOUR(COALESCE(placed_at, created_at)) b, SUM(sub_total) v')
                ->groupBy('b')->pluck('v', 'b');
            for ($h = 0; $h < 24; $h++) {
                $cats[] = $h.'h';
                $data[] = round(((int) ($rows[$h] ?? 0)) / 100, 2);
            }

            return [$cats, $data];
        }

        if ($period === '12m') {
            $rows = $this->ordersBase()
                ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
                ->selectRaw("DATE_FORMAT(COALESCE(placed_at, created_at), '%Y-%m') b, SUM(sub_total) v")
                ->groupBy('b')->pluck('v', 'b');
            $cursor = $start;
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m');
                $cats[] = $cursor->locale('fr')->isoFormat('MMM');
                $data[] = round(((int) ($rows[$key] ?? 0)) / 100, 2);
                $cursor = $cursor->addMonth();
            }

            return [$cats, $data];
        }

        // 7j / 30j : buckets journaliers
        $days = $period === '7j' ? 7 : 30;
        $rows = $this->ordersBase()
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
            ->selectRaw('DATE(COALESCE(placed_at, created_at)) b, SUM(sub_total) v')
            ->groupBy('b')->pluck('v', 'b');
        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i);
            $key = $day->format('Y-m-d');
            $cats[] = $period === '7j' ? $day->locale('fr')->isoFormat('dd') : (string) $day->day;
            $data[] = round(((int) ($rows[$key] ?? 0)) / 100, 2);
        }

        return [$cats, $data];
    }

    /** Petite série journalière (16 pts) pour les sparklines KPI. */
    private function dailySeries(CarbonImmutable $start, CarbonImmutable $end, string $mode = 'revenue'): array
    {
        $rows = $this->ordersBase()
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start->subDays(15), $end])
            ->selectRaw('DATE(COALESCE(placed_at, created_at)) b, SUM(sub_total) rev, COUNT(*) cnt')
            ->groupBy('b')->get()->keyBy('b');

        $out = [];
        for ($i = 15; $i >= 0; $i--) {
            $key = $end->subDays($i)->format('Y-m-d');
            $r = $rows[$key] ?? null;
            $rev = (int) ($r->rev ?? 0);
            $cnt = (int) ($r->cnt ?? 0);
            $out[] = match ($mode) {
                'count' => $cnt,
                'aov' => $cnt > 0 ? $rev / $cnt : 0,
                default => $rev,
            };
        }

        return $out;
    }

    /** Convertit une série de valeurs en points SVG polyline (viewBox 220×40). */
    private function sparkFrom(array $arr): string
    {
        $arr = array_map('floatval', $arr);
        $n = max(count($arr), 2);
        $min = min($arr);
        $max = max($arr);
        $range = ($max - $min) ?: 1;
        $pts = [];
        foreach ($arr as $i => $v) {
            $x = ($i / ($n - 1)) * 220;
            $y = 40 - (($v - $min) / $range) * 34 - 3;
            $pts[] = round($x, 1).','.round($y, 1);
        }

        return implode(' ', $pts);
    }

    // ------------------------------------------------------ statut commandes

    private function statusBreakdown(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = $this->ordersBase()
            ->whereBetween(DB::raw('COALESCE(placed_at, created_at)'), [$start, $end])
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        $buckets = ['paid' => 0, 'toship' => 0, 'pending' => 0, 'refunded' => 0];
        foreach ($rows as $status => $count) {
            $bucket = self::STATUS_BUCKET[$status] ?? 'pending';
            $buckets[$bucket] += (int) $count;
        }

        $meta = [
            'paid' => ['Payées', '#136356'],
            'toship' => ['À expédier', '#aac932'],
            'pending' => ['En attente', '#e8a317'],
            'refunded' => ['Remboursées', '#d64545'],
        ];
        $total = array_sum($buckets) ?: 1;

        $labels = $data = $colors = $legend = [];
        foreach ($meta as $k => [$label, $color]) {
            $labels[] = $label;
            $data[] = $buckets[$k];
            $colors[] = $color;
            $legend[] = [
                'label' => $label,
                'value' => number_format((float) $buckets[$k], 0, ',', ' '),
                'pct' => round($buckets[$k] / $total * 100).' %',
                'color' => $color,
            ];
        }

        return compact('labels', 'data', 'colors', 'legend');
    }

    // ------------------------------------------------------- meilleures ventes

    private function bestSellers(): array
    {
        $rows = DB::table('lunar_order_lines')
            ->where('type', 'physical')
            ->selectRaw('description, MAX(identifier) sku, SUM(quantity) qty, SUM(sub_total) rev')
            ->groupBy('description')
            ->orderByDesc('qty')
            ->limit(8)->get();

        return $rows->map(fn ($r) => [
            'name' => $r->description ?: 'Produit',
            'sku' => $r->sku ?: '—',
            'qty' => (int) $r->qty,
            'rev' => $this->eur((int) $r->rev),
        ])->all();
    }

    // ------------------------------------------------------- ventes catégorie

    private function categories(): array
    {
        $rows = DB::table('lunar_order_lines as ol')
            ->join('lunar_product_variants as pv', function ($j) {
                $j->on('pv.id', '=', 'ol.purchasable_id')->where('ol.purchasable_type', 'like', '%ProductVariant');
            })
            ->join('lunar_collection_product as cp', 'cp.product_id', '=', 'pv.product_id')
            ->selectRaw('cp.collection_id, SUM(ol.sub_total) rev')
            ->groupBy('cp.collection_id')
            ->orderByDesc('rev')
            ->limit(6)->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'data' => [], 'colors' => [], 'legend' => []];
        }

        $names = Collection::query()->whereIn('id', $rows->pluck('collection_id'))->get()
            ->mapWithKeys(fn ($c) => [$c->id => (string) $c->translateAttribute('name')]);

        $palette = ['#00453e', '#136356', '#43847a', '#aac932', '#c8dd72', '#9aa3a0'];
        $total = max((float) $rows->sum('rev'), 1);

        $labels = $data = $colors = $legend = [];
        foreach ($rows->values() as $i => $r) {
            $label = $names[$r->collection_id] ?? 'Catégorie';
            $pct = round($r->rev / $total * 100);
            $labels[] = $label;
            $data[] = (int) $pct;
            $colors[] = $palette[$i % count($palette)];
            $legend[] = ['label' => $label, 'pct' => $pct.' %', 'color' => $palette[$i % count($palette)]];
        }

        return compact('labels', 'data', 'colors', 'legend');
    }

    // ---------------------------------------------------------- par région

    private function regions(): array
    {
        $rows = DB::table('lunar_order_addresses')
            ->where('type', 'shipping')
            ->whereNotNull('state')->where('state', '!=', '')
            ->selectRaw('state, COUNT(*) c')
            ->groupBy('state')->orderByDesc('c')->limit(8)->get();

        return [
            'labels' => $rows->pluck('state')->all(),
            'data' => $rows->pluck('c')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    // -------------------------------------------------------- typologie clients

    private function clientTypes(): array
    {
        $rows = DB::table('lunar_orders as o')
            ->whereNotNull('o.placed_at')
            ->leftJoin('lunar_customer_customer_group as cg', 'cg.customer_id', '=', 'o.customer_id')
            ->leftJoin('lunar_customer_groups as g', 'g.id', '=', 'cg.customer_group_id')
            ->selectRaw("COALESCE(g.name, 'Particuliers') name, SUM(o.sub_total) rev")
            ->groupBy('name')->orderByDesc('rev')->get();

        if ($rows->isEmpty()) {
            return ['labels' => [], 'data' => [], 'colors' => [], 'legend' => []];
        }

        $palette = ['#00453e', '#aac932', '#79aaa1', '#43847a'];
        $total = max((float) $rows->sum('rev'), 1);

        $labels = $data = $colors = $legend = [];
        foreach ($rows->values() as $i => $r) {
            $pct = round($r->rev / $total * 100);
            $labels[] = $r->name;
            $data[] = (int) $pct;
            $colors[] = $palette[$i % count($palette)];
            $legend[] = ['label' => $r->name, 'pct' => $pct.' %', 'color' => $palette[$i % count($palette)]];
        }

        return compact('labels', 'data', 'colors', 'legend');
    }

    // --------------------------------------------------------------- tunnel

    private function funnel(CarbonImmutable $start, CarbonImmutable $end, \Closure $rng): array
    {
        // Bas du tunnel = réel. Haut (sessions/visiteurs, ajouts panier) = simulé
        // faute d'analytics web trackées → badge « stat à connecter ».
        $paid = $this->ordersAgg($start, $end)->orders;
        $carts = DB::table('lunar_carts')->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end])
            ->whereExists(fn ($q) => $q->from('lunar_cart_lines')->whereColumn('lunar_cart_lines.cart_id', 'lunar_carts.id'))
            ->count();

        $checkout = max($paid, (int) round($paid * (1.4 + $rng() * 0.6)) + $carts);
        $cart = max($checkout, $carts, (int) round($checkout * (2.0 + $rng())));
        $sessions = max($cart * (int) round(3 + $rng() * 4), $cart + 1);

        $steps = [
            ['Sessions', $sessions, '#00453e', true],
            ['Ajouts au panier', $cart, '#136356', true],
            ['Devis / Checkout', $checkout, '#43847a', true],
            ['Commandes payées', max($paid, 0), '#aac932', false],
        ];
        $topValue = max($steps[0][1], 1);

        $out = [];
        foreach ($steps as $i => [$label, $value, $color, $simulated]) {
            $rate = $i === 0
                ? '100 % du trafic'
                : round($value / max($steps[$i - 1][1], 1) * 100).' % de l’étape préc.';
            $out[] = [
                'label' => $label,
                'value' => number_format((float) $value, 0, ',', ' '),
                'width' => round($value / $topValue * 100).'%',
                'color' => $color,
                'rate' => $rate,
                'simulated' => $simulated,
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------- stock

    private function stock(): array
    {
        $rows = DB::table('lunar_product_variants')
            ->whereNull('deleted_at')
            ->whereRaw('stock <= GREATEST(COALESCE(min_quantity,0), 10)')
            ->orderBy('stock')
            ->limit(6)
            ->get(['sku', 'stock', 'min_quantity']);

        return $rows->map(function ($r) {
            $seuil = max((int) ($r->min_quantity ?? 0), 10);
            $stock = (int) $r->stock;
            if ($stock <= 0) {
                $status = 'Rupture';
                $tone = 'danger';
            } elseif ($stock <= max(1, (int) round($seuil * 0.3))) {
                $status = 'Critique';
                $tone = 'warning';
            } else {
                $status = 'Bas';
                $tone = 'muted';
            }

            return [
                'name' => $r->sku ?: 'Variante',
                'sku' => $r->sku ?: '—',
                'stock' => $stock,
                'seuil' => $seuil,
                'status' => $status,
                'tone' => $tone,
            ];
        })->all();
    }

    private function ruptureCount(): int
    {
        return DB::table('lunar_product_variants')->whereNull('deleted_at')->where('stock', '<=', 0)->count();
    }

    // ----------------------------------------------------------------- devis

    private function ordersDevisQuery()
    {
        return DB::table('lunar_orders')->where('status', 'awaiting-quote');
    }

    private function devisPotential(): int
    {
        return (int) $this->ordersDevisQuery()->sum('total');
    }

    private function devis(): array
    {
        $rows = DB::table('lunar_orders as o')
            ->where('o.status', 'awaiting-quote')
            ->leftJoin('lunar_order_addresses as a', function ($j) {
                $j->on('a.order_id', '=', 'o.id')->where('a.type', 'billing');
            })
            ->orderByDesc('o.created_at')
            ->limit(4)
            ->get(['o.reference', 'o.total', 'o.created_at', 'a.first_name', 'a.last_name', 'a.company_name']);

        return $rows->map(function ($r) {
            $name = trim((string) ($r->company_name ?: trim(($r->first_name ?? '').' '.($r->last_name ?? '')))) ?: 'Client';

            return [
                'client' => $name,
                'initials' => $this->initials($name),
                'ref' => $r->reference ?: '—',
                'date' => Carbon::parse($r->created_at)->locale('fr')->isoFormat('D MMM'),
                'amount' => $this->eur((int) $r->total),
                'status' => 'En attente',
                'tone' => 'warning',
            ];
        })->all();
    }

    // --------------------------------------------------------------- promos

    private function promos(\Closure $rng): array
    {
        $now = CarbonImmutable::now();
        $activeDiscounts = DB::table('lunar_discounts')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->count();

        $members = DB::table('pko_loyalty_customer_points')->count();
        $points = (int) DB::table('pko_loyalty_customer_points')->sum('total_points');

        // Marge brute : pas de prix d'achat stocké → simulé + badge.
        $margin = 28 + $rng() * 12;

        return [
            [
                'key' => 'discounts', 'variant' => 'brand',
                'title' => 'Réductions actives', 'icon' => 'tag',
                'value' => $activeDiscounts.' '.($activeDiscounts > 1 ? 'codes' : 'code'),
                'sub' => 'Remises programmées en cours',
            ],
            [
                'key' => 'loyalty', 'variant' => 'plain',
                'title' => 'Programme fidélité', 'icon' => 'medal',
                'value' => number_format((float) $members, 0, ',', ' ').' membres',
                'sub' => number_format((float) $points, 0, ',', ' ').' points cumulés',
            ],
            [
                'key' => 'margin', 'variant' => 'plain',
                'title' => 'Marge brute moyenne', 'icon' => 'chart',
                'value' => number_format($margin, 1, ',', ' ').' %',
                'sub' => 'estimation', 'simulated' => true,
            ],
        ];
    }

    // ------------------------------------------------------- dernières commandes

    private function recentOrders(): array
    {
        $rows = DB::table('lunar_orders as o')
            ->leftJoin('lunar_order_addresses as a', function ($j) {
                $j->on('a.order_id', '=', 'o.id')->where('a.type', 'billing');
            })
            ->leftJoin('lunar_channels as ch', 'ch.id', '=', 'o.channel_id')
            ->orderByDesc(DB::raw('COALESCE(o.placed_at, o.created_at)'))
            ->limit(12)
            ->get(['o.reference', 'o.status', 'o.total', 'o.placed_at', 'o.created_at', 'ch.name as channel', 'a.first_name', 'a.last_name', 'a.company_name']);

        $meta = [
            'paid' => ['Payée', 'success'],
            'toship' => ['À expédier', 'info'],
            'pending' => ['En attente', 'warning'],
            'refunded' => ['Remboursée', 'danger'],
        ];

        return $rows->map(function ($r) use ($meta) {
            $bucket = self::STATUS_BUCKET[$r->status] ?? 'pending';
            [, $tone] = $meta[$bucket];
            $name = trim((string) ($r->company_name ?: trim(($r->first_name ?? '').' '.($r->last_name ?? '')))) ?: 'Client';

            return [
                'num' => $r->reference ?: '—',
                'client' => $name,
                'initials' => $this->initials($name),
                'channel' => $r->channel ?: 'Boutique',
                'date' => Carbon::parse($r->placed_at ?? $r->created_at)->locale('fr')->isoFormat('D MMM YYYY'),
                'status' => $r->status,
                'statusBucket' => $bucket,
                'statusLabel' => OrderStatusLabel::of((string) $r->status),
                'tone' => $tone,
                'total' => $this->eur2((int) $r->total),
            ];
        })->all();
    }

    // -------------------------------------------------------------- helpers

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));
        if (count($parts) === 0) {
            return '—';
        }
        $first = mb_substr($parts[0], 0, 1);
        $second = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$second);
    }

    /** Montant cents → « 1 234 € ». */
    private function eur(int $cents): string
    {
        return number_format($cents / 100, 0, ',', ' ').' €';
    }

    /** Montant cents → « 1 234,56 € ». */
    private function eur2(float $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }

    /**
     * PRNG déterministe (LCG Park-Miller / minimal standard) → valeurs simulées
     * stables par période. Reste dans les bornes int64 (pas d'overflow float).
     */
    private function seededRng(int $seed): \Closure
    {
        $state = $seed % 2147483647;
        if ($state <= 0) {
            $state += 2147483646;
        }

        return function () use (&$state): float {
            $state = ($state * 16807) % 2147483647;

            return $state / 2147483647;
        };
    }
}
