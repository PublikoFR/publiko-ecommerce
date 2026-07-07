{{--
    Tableau de bord Weklo — vue design importée depuis le Claude Design System.
    Toutes les données (4 périodes) sont pré-calculées côté serveur par
    App\Support\Dashboard\DashboardStats et pilotées côté client par Alpine :
    changement de période / filtres / recherche 100 % client → aucun aller-retour
    Livewire (évite le crash ApexCharts ↔ Livewire).
    Les 3 métriques sans source réelle portent un badge « stat à connecter ».
--}}
<x-filament-panels::page>
    <div
        class="wk-dash"
        x-data="wkDashboard(@js($this->getDashboardData()))"
        x-cloak
        style="font-family:var(--font-sans);color:var(--text-primary)"
    >
        {{-- ===================== EN-TÊTE PAGE ===================== --}}
        <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap">
            <div>
                <h1 class="wk-display" style="font-weight:700;font-size:30px;margin:0;color:var(--forest-600)">Tableau de bord</h1>
                <p style="margin:6px 0 0;color:var(--text-secondary);font-size:14px">
                    Vue d'ensemble de l'activité — <span style="font-weight:600;color:var(--text-primary)" x-text="d.periodLabel"></span>
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <button type="button" class="wk-chip" style="display:flex;align-items:center;gap:8px;background:var(--surface-card);border:1px solid var(--border-default);border-radius:10px;padding:9px 14px;font-size:13px;font-weight:600;color:var(--text-secondary);font-family:var(--font-sans)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v11"/><path d="m7 10 5 5 5-5"/><path d="M4 20h16"/></svg>
                    Exporter
                </button>
                <button type="button" @click="refresh()" style="display:flex;align-items:center;gap:8px;background:var(--forest-600);border:1px solid var(--forest-600);border-radius:10px;padding:9px 16px;font-size:13px;font-weight:600;color:#fff;cursor:pointer;font-family:var(--font-sans)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-.5 4"/><path d="M20 5v5h-5"/></svg>
                    Actualiser
                </button>
            </div>
        </div>

        {{-- ===================== BARRE DE FILTRES ===================== --}}
        <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:14px 18px;display:flex;align-items:center;gap:18px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:6px;background:var(--surface-sunken);border-radius:10px;padding:4px">
                <template x-for="t in periodTabs" :key="t.key">
                    <span class="wk-seg" @click="period = t.key"
                        :style="period === t.key
                            ? 'padding:7px 15px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;background:var(--forest-600);box-shadow:var(--shadow-sm)'
                            : 'padding:7px 15px;border-radius:8px;font-size:13px;font-weight:600;color:var(--text-secondary);background:transparent'"
                        x-text="t.label"></span>
                </template>
            </div>
            <div class="wk-chip" style="display:flex;align-items:center;gap:9px;color:var(--text-secondary);font-size:13px;border:1px solid var(--border-default);border-radius:10px;padding:8px 13px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--forest-600)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>
                <span style="font-weight:600;color:var(--text-primary)" x-text="d.dateRange"></span>
            </div>
            <label style="display:flex;align-items:center;gap:10px;margin-left:auto;cursor:pointer" @click="compare = !compare">
                <span style="font-size:13px;font-weight:600;color:var(--text-secondary)">Comparer à la période précédente</span>
                <span :style="'width:42px;height:24px;border-radius:9999px;position:relative;transition:background .2s var(--ease-standard);flex-shrink:0;background:' + (compare ? 'var(--forest-600)' : 'var(--neutral-300)')">
                    <span :style="'position:absolute;top:2px;width:20px;height:20px;border-radius:9999px;background:#fff;box-shadow:var(--shadow-sm);transition:left .2s var(--ease-emphasis);left:' + (compare ? '20px' : '2px')"></span>
                </span>
            </label>
        </div>

        {{-- ===================== KPI CARDS ===================== --}}
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px">
            <template x-for="k in d.kpis" :key="k.key">
                <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:18px 20px;display:flex;flex-direction:column;gap:8px;min-width:0">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
                        <span style="font-size:12px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;color:var(--text-muted)" x-text="k.label"></span>
                        <span style="display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:var(--forest-50);color:var(--forest-600)" x-html="iconFor(k.icon)"></span>
                    </div>
                    <div class="wk-display" style="font-weight:700;font-size:27px;line-height:1.1;color:var(--text-primary)" x-text="k.value"></div>
                    <div style="display:flex;align-items:center;gap:6px;font-size:12.5px;flex-wrap:wrap">
                        <span :style="'display:inline-flex;align-items:center;gap:3px;font-weight:700;padding:2px 7px;border-radius:9999px;' + (k.up ? 'background:var(--success-100);color:var(--success-700)' : 'background:var(--danger-100);color:var(--danger-700)')">
                            <svg x-show="k.up" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 15 6-6 6 6"/></svg>
                            <svg x-show="!k.up" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            <span x-text="k.delta"></span>
                        </span>
                        <span style="color:var(--text-muted)">vs <span x-text="d.prevLabel"></span></span>
                        <template x-if="k.simulated"><span x-html="simBadge()"></span></template>
                    </div>
                    <svg viewBox="0 0 220 40" preserveAspectRatio="none" style="width:100%;height:34px;margin-top:2px">
                        <polyline :points="k.spark" fill="none" stroke="var(--forest-400)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </template>
        </div>

        {{-- ===================== MINI STATS ===================== --}}
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px">
            <template x-for="s in d.miniStats" :key="s.key">
                <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:16px 18px;display:flex;align-items:center;gap:14px">
                    <span :style="'display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;flex-shrink:0;' + toneSoft(s.tone)" x-html="iconFor(s.icon)"></span>
                    <div style="min-width:0">
                        <div class="wk-display" style="font-weight:700;font-size:22px;line-height:1.1;color:var(--text-primary)" x-text="s.value"></div>
                        <div style="font-size:12.5px;color:var(--text-secondary);font-weight:500" x-text="s.label"></div>
                        <div :style="'font-size:11.5px;font-weight:600;margin-top:2px;color:' + toneText(s.subTone)" x-text="s.sub"></div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ===================== CA + STATUTS ===================== --}}
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px">
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;min-width:0">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                    <div>
                        <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--text-primary)">Chiffre d'affaires</h3>
                        <p style="margin:3px 0 0;font-size:12.5px;color:var(--text-muted)">Évolution sur la période — HT</p>
                    </div>
                    <div style="display:flex;align-items:center;gap:14px;font-size:12px;color:var(--text-secondary)">
                        <span style="display:flex;align-items:center;gap:6px"><span style="width:20px;height:3px;border-radius:2px;background:var(--forest-600)"></span>Période</span>
                        <span x-show="compare" style="display:flex;align-items:center;gap:6px"><span style="width:20px;height:3px;border-radius:2px;background:var(--neutral-400)"></span>Préc.</span>
                    </div>
                </div>
                <div x-ref="chartCa" style="width:100%;height:300px"></div>
            </div>
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;display:flex;flex-direction:column;min-width:0">
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;color:var(--text-primary)">Statut des commandes</h3>
                <p style="margin:0 0 6px;font-size:12.5px;color:var(--text-muted)">Répartition — <span x-text="d.periodLabel"></span></p>
                <div x-ref="chartStatus" style="width:100%;height:190px"></div>
                <div style="display:flex;flex-direction:column;gap:9px;margin-top:8px">
                    <template x-for="l in d.status.legend" :key="l.label">
                        <div style="display:flex;align-items:center;gap:9px;font-size:13px">
                            <span :style="'width:10px;height:10px;border-radius:3px;flex-shrink:0;background:' + l.color"></span>
                            <span style="color:var(--text-secondary)" x-text="l.label"></span>
                            <span style="margin-left:auto;font-weight:700;color:var(--text-primary)" x-text="l.value"></span>
                            <span style="color:var(--text-muted);width:44px;text-align:right" x-text="l.pct"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ===================== BEST SELLERS + CATÉGORIES ===================== --}}
        <div style="display:grid;grid-template-columns:1.55fr 1fr;gap:18px">
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;min-width:0">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px">
                    <div>
                        <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--text-primary)">Meilleures ventes</h3>
                        <p style="margin:3px 0 0;font-size:12.5px;color:var(--text-muted)">Top produits sur 12 mois glissants</p>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr auto auto auto;gap:4px 14px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:var(--text-muted);padding:0 0 8px;border-bottom:1px solid var(--border-subtle)">
                    <span>Produit</span><span style="text-align:center">Réf.</span><span style="text-align:center">Qté</span><span style="text-align:right">CA HT</span>
                </div>
                <template x-for="(p, i) in d.bestSellers" :key="i">
                    <div class="wk-row" style="display:grid;grid-template-columns:1fr auto auto auto;gap:4px 14px;align-items:center;font-size:13.5px;padding:10px 0;border-bottom:1px solid var(--border-subtle)">
                        <div style="display:flex;align-items:center;gap:11px;min-width:0">
                            <span style="width:30px;height:30px;border-radius:8px;background:var(--forest-50);color:var(--forest-600);display:flex;align-items:center;justify-content:center;flex-shrink:0" x-html="iconFor('box')"></span>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500" x-text="p.name"></span>
                        </div>
                        <span class="wk-mono" style="font-size:12px;color:var(--text-muted);text-align:center" x-text="p.sku"></span>
                        <span style="text-align:center;font-weight:600;color:var(--text-secondary)" x-text="p.qty"></span>
                        <span style="text-align:right;font-weight:700" x-text="p.rev"></span>
                    </div>
                </template>
                <p x-show="d.bestSellers.length === 0" style="text-align:center;color:var(--text-muted);font-size:13px;padding:24px 0;margin:0">Aucune vente sur la période.</p>
            </div>
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;display:flex;flex-direction:column;min-width:0">
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;color:var(--text-primary)">Ventes par catégorie</h3>
                <p style="margin:0 0 6px;font-size:12.5px;color:var(--text-muted)">Part du CA — 12 mois</p>
                <div x-ref="chartCategories" style="width:100%;height:180px"></div>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                    <template x-for="c in d.categories.legend" :key="c.label">
                        <div style="display:flex;align-items:center;gap:9px;font-size:13px">
                            <span :style="'width:10px;height:10px;border-radius:3px;flex-shrink:0;background:' + c.color"></span>
                            <span style="color:var(--text-secondary)" x-text="c.label"></span>
                            <span style="margin-left:auto;font-weight:700;color:var(--text-primary)" x-text="c.pct"></span>
                        </div>
                    </template>
                    <p x-show="d.categories.legend.length === 0" style="color:var(--text-muted);font-size:12.5px;margin:8px 0 0">Pas encore de données.</p>
                </div>
            </div>
        </div>

        {{-- ===================== RÉGIONS + CLIENTS + TUNNEL ===================== --}}
        <div style="display:grid;grid-template-columns:1.15fr 1fr 1fr;gap:18px">
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;min-width:0">
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;color:var(--text-primary)">Commandes par région</h3>
                <p style="margin:0 0 4px;font-size:12.5px;color:var(--text-muted)">Adresse de livraison</p>
                <div x-ref="chartRegions" style="width:100%;height:290px"></div>
                <p x-show="d.regions.data.length === 0" style="text-align:center;color:var(--text-muted);font-size:12.5px;margin:24px 0 0">Pas de données région.</p>
            </div>
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;display:flex;flex-direction:column;min-width:0">
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;color:var(--text-primary)">Typologie clients</h3>
                <p style="margin:0 0 6px;font-size:12.5px;color:var(--text-muted)">Répartition du CA</p>
                <div x-ref="chartClients" style="width:100%;height:170px"></div>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                    <template x-for="c in d.clientTypes.legend" :key="c.label">
                        <div style="display:flex;align-items:center;gap:9px;font-size:13px">
                            <span :style="'width:10px;height:10px;border-radius:3px;flex-shrink:0;background:' + c.color"></span>
                            <span style="color:var(--text-secondary)" x-text="c.label"></span>
                            <span style="margin-left:auto;font-weight:700;color:var(--text-primary)" x-text="c.pct"></span>
                        </div>
                    </template>
                </div>
            </div>
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;min-width:0">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px">
                    <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--text-primary)">Tunnel de conversion</h3>
                    <span x-html="simBadge()"></span>
                </div>
                <p style="margin:0 0 12px;font-size:12.5px;color:var(--text-muted)">Du visiteur à la commande</p>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <template x-for="(f, i) in d.funnel" :key="i">
                        <div>
                            <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;margin-bottom:4px">
                                <span style="color:var(--text-secondary);font-weight:500" x-text="f.label"></span>
                                <span style="font-weight:700;color:var(--text-primary)" x-text="f.value"></span>
                            </div>
                            <div style="height:10px;border-radius:9999px;background:var(--surface-sunken);overflow:hidden">
                                <div :style="'height:100%;border-radius:9999px;width:' + f.width + ';background:' + f.color"></div>
                            </div>
                            <div style="font-size:11.5px;color:var(--text-muted);margin-top:3px" x-text="f.rate"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ===================== STOCK + DEVIS ===================== --}}
        <div style="display:grid;grid-template-columns:1.35fr 1fr;gap:18px">
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;min-width:0">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <div>
                        <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--text-primary)">Stock à surveiller</h3>
                        <p style="margin:3px 0 0;font-size:12.5px;color:var(--text-muted)">Produits sous le seuil de réapprovisionnement</p>
                    </div>
                    <span style="font-size:12px;font-weight:700;color:var(--danger-700);background:var(--danger-100);padding:3px 10px;border-radius:9999px"><span x-text="d.ruptureCount"></span> en rupture</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr auto auto auto;gap:4px 14px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:var(--text-muted);padding:0 0 8px;border-bottom:1px solid var(--border-subtle)">
                    <span>Produit</span><span style="text-align:center">Stock</span><span style="text-align:center">Seuil</span><span style="text-align:right">Statut</span>
                </div>
                <template x-for="(s, i) in d.stock" :key="i">
                    <div class="wk-row" style="display:grid;grid-template-columns:1fr auto auto auto;gap:4px 14px;align-items:center;font-size:13.5px;padding:9px 0;border-bottom:1px solid var(--border-subtle)">
                        <div style="min-width:0">
                            <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="s.name"></div>
                            <div class="wk-mono" style="font-size:11px;color:var(--text-muted)" x-text="s.sku"></div>
                        </div>
                        <span :style="'text-align:center;font-weight:700;color:' + toneText(s.tone)" x-text="s.stock"></span>
                        <span style="text-align:center;color:var(--text-muted)" x-text="s.seuil"></span>
                        <span style="text-align:right"><span :style="'font-size:11.5px;font-weight:700;padding:2px 9px;border-radius:9999px;' + toneSoft(s.tone)" x-text="s.status"></span></span>
                    </div>
                </template>
                <p x-show="d.stock.length === 0" style="text-align:center;color:var(--text-muted);font-size:13px;padding:24px 0;margin:0">Aucun produit sous le seuil.</p>
            </div>
            <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;min-width:0">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <div>
                        <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--text-primary)">Devis en cours</h3>
                        <p style="margin:3px 0 0;font-size:12.5px;color:var(--text-muted)">Potentiel : <span style="font-weight:700;color:var(--forest-600)" x-text="d.devisTotal"></span></p>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <template x-for="(v, i) in d.devis" :key="i">
                        <div class="wk-row" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--border-subtle);border-radius:12px">
                            <span style="width:36px;height:36px;border-radius:9999px;background:var(--forest-50);color:var(--forest-600);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0" x-text="v.initials"></span>
                            <div style="min-width:0;flex:1">
                                <div style="font-weight:600;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="v.client"></div>
                                <div style="font-size:11.5px;color:var(--text-muted)"><span x-text="v.ref"></span> · <span x-text="v.date"></span></div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-weight:700;font-size:14px" x-text="v.amount"></div>
                                <span :style="'font-size:11px;font-weight:700;padding:1px 8px;border-radius:9999px;' + toneSoft(v.tone)" x-text="v.status"></span>
                            </div>
                        </div>
                    </template>
                    <p x-show="d.devis.length === 0" style="text-align:center;color:var(--text-muted);font-size:13px;padding:20px 0;margin:0">Aucun devis en attente.</p>
                </div>
            </div>
        </div>

        {{-- ===================== PROMOS / FIDÉLITÉ / MARGE ===================== --}}
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px">
            <template x-for="p in d.promos" :key="p.key">
                <div :style="p.variant === 'brand'
                    ? 'background:var(--forest-600);border:1px solid var(--forest-600);border-radius:16px;box-shadow:var(--shadow-sm);padding:18px 20px'
                    : 'background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:18px 20px'">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                        <span :style="'width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;' + (p.variant === 'brand' ? 'background:rgba(255,255,255,.15);color:#fff' : 'background:var(--lime-100);color:var(--lime-700)')" x-html="iconFor(p.icon)"></span>
                        <span :style="'font-size:13.5px;font-weight:700;' + (p.variant === 'brand' ? 'color:rgba(255,255,255,.9)' : 'color:var(--text-secondary)')" x-text="p.title"></span>
                        <template x-if="p.simulated"><span x-html="simBadge()"></span></template>
                    </div>
                    <div class="wk-display" :style="'font-weight:700;font-size:25px;line-height:1.1;' + (p.variant === 'brand' ? 'color:#fff' : 'color:var(--text-primary)')" x-text="p.value"></div>
                    <div :style="'font-size:12.5px;margin-top:4px;' + (p.variant === 'brand' ? 'color:rgba(255,255,255,.7)' : 'color:var(--text-muted)')" x-text="p.sub"></div>
                </div>
            </template>
        </div>

        {{-- ===================== DERNIÈRES COMMANDES ===================== --}}
        <div style="background:var(--surface-card);border:1px solid var(--border-subtle);border-radius:16px;box-shadow:var(--shadow-sm);padding:20px 22px;min-width:0">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:14px">
                <div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--text-primary)">Dernières commandes</h3>
                    <p style="margin:3px 0 0;font-size:12.5px;color:var(--text-muted)"><span x-text="filteredOrders.length"></span> commandes affichées</p>
                </div>
                <div style="display:flex;align-items:center;gap:8px;background:var(--surface-sunken);border:1px solid var(--border-subtle);border-radius:9999px;padding:7px 14px;min-width:230px">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input x-model="query" placeholder="Filtrer par client ou n°…" style="border:none;background:transparent;outline:none;font-size:13px;font-family:var(--font-sans);color:var(--text-primary);width:100%">
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px">
                <template x-for="f in orderFilters" :key="f.key">
                    <span class="wk-seg" @click="orderFilter = f.key"
                        :style="'font-size:12.5px;font-weight:600;padding:6px 14px;border-radius:9999px;border:1px solid;' + (orderFilter === f.key ? 'color:#fff;background:var(--forest-600);border-color:var(--forest-600)' : 'color:var(--text-secondary);background:var(--surface-card);border-color:var(--border-default)')">
                        <span x-text="f.label"></span> <span style="opacity:.7" x-text="f.count"></span>
                    </span>
                </template>
            </div>
            <div style="overflow-x:auto">
                <div style="display:grid;grid-template-columns:1.1fr 1.6fr 1fr 1.1fr .9fr .9fr;gap:14px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:var(--text-muted);padding:0 4px 10px;border-bottom:1px solid var(--border-subtle);min-width:760px">
                    <span>N° commande</span><span>Client</span><span>Canal</span><span>Date</span><span>Statut</span><span style="text-align:right">Total TTC</span>
                </div>
                <div style="min-width:760px">
                    <template x-for="(o, i) in filteredOrders" :key="i">
                        <div class="wk-row" style="display:grid;grid-template-columns:1.1fr 1.6fr 1fr 1.1fr .9fr .9fr;gap:14px;align-items:center;font-size:13.5px;padding:12px 4px;border-bottom:1px solid var(--border-subtle)">
                            <span class="wk-mono" style="font-weight:600;color:var(--forest-600)" x-text="o.num"></span>
                            <div style="display:flex;align-items:center;gap:10px;min-width:0">
                                <span style="width:28px;height:28px;border-radius:9999px;background:var(--surface-sunken);color:var(--text-secondary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0" x-text="o.initials"></span>
                                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="o.client"></span>
                            </div>
                            <span style="color:var(--text-secondary)" x-text="o.channel"></span>
                            <span style="color:var(--text-secondary)" x-text="o.date"></span>
                            <span><span :style="'font-size:11.5px;font-weight:700;padding:2px 9px;border-radius:9999px;' + toneSoft(o.tone)" x-text="o.statusLabel"></span></span>
                            <span style="text-align:right;font-weight:700" x-text="o.total"></span>
                        </div>
                    </template>
                </div>
                <p x-show="filteredOrders.length === 0" style="text-align:center;color:var(--text-muted);font-size:13px;padding:24px 0;margin:0">Aucune commande ne correspond au filtre.</p>
            </div>
        </div>
    </div>

    {{-- ApexCharts (CDN) — chargé une fois, l'init Alpine attend sa disponibilité --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>

    <script>
        window.wkDashboard = function (data) {
            return {
                data: data,
                period: '30j',
                compare: true,
                orderFilter: 'all',
                query: '',
                charts: {},

                get d() { return this.data[this.period]; },

                get periodTabs() {
                    return [
                        { key: 'jour', label: 'Jour' },
                        { key: '7j', label: '7 jours' },
                        { key: '30j', label: '30 jours' },
                        { key: '12m', label: '12 mois' },
                    ];
                },

                get orderFilters() {
                    const orders = this.d.orders;
                    const c = (b) => orders.filter(o => o.statusBucket === b).length;
                    return [
                        { key: 'all', label: 'Toutes', count: orders.length },
                        { key: 'paid', label: 'Payées', count: c('paid') },
                        { key: 'toship', label: 'À expédier', count: c('toship') },
                        { key: 'pending', label: 'En attente', count: c('pending') },
                        { key: 'refunded', label: 'Remboursées', count: c('refunded') },
                    ];
                },

                get filteredOrders() {
                    const q = this.query.trim().toLowerCase();
                    return this.d.orders.filter(o =>
                        (this.orderFilter === 'all' || o.statusBucket === this.orderFilter) &&
                        (!q || o.client.toLowerCase().includes(q) || o.num.toLowerCase().includes(q))
                    );
                },

                init() {
                    this.compare = this.d.compare;
                    this.whenApexReady(() => {
                        this.renderStatic();
                        this.renderDynamic();
                    });
                    this.$watch('period', () => this.renderDynamic());
                    this.$watch('compare', () => this.renderDynamic());
                },

                refresh() {
                    this.renderStatic();
                    this.renderDynamic();
                },

                whenApexReady(cb) {
                    if (window.ApexCharts) { cb(); return; }
                    const t = setInterval(() => {
                        if (window.ApexCharts) { clearInterval(t); cb(); }
                    }, 120);
                },

                mk(ref, opts) {
                    const el = this.$refs[ref];
                    if (!el) return null;
                    const c = new ApexCharts(el, opts);
                    c.render();
                    return c;
                },
                destroy(key) { try { this.charts[key] && this.charts[key].destroy(); } catch (e) {} },

                // -- graphes dépendants de la période (CA + statuts) --
                renderDynamic() {
                    this.destroy('ca'); this.destroy('status');
                    this.charts.ca = this.mk('chartCa', this.caOptions());
                    this.charts.status = this.mk('chartStatus', this.donutOptions(this.d.status.labels, this.d.status.data, this.d.status.colors));
                },

                // -- graphes statiques (catégories, clients, régions) --
                renderStatic() {
                    this.destroy('cats'); this.destroy('clients'); this.destroy('regions');
                    const cats = this.d.categories;
                    this.charts.cats = this.mk('chartCategories', this.donutOptions(cats.labels, cats.data, cats.colors));
                    const cl = this.d.clientTypes;
                    this.charts.clients = this.mk('chartClients', this.donutOptions(cl.labels, cl.data, cl.colors));
                    this.charts.regions = this.mk('chartRegions', this.barOptions(this.d.regions.labels, this.d.regions.data));
                },

                caOptions() {
                    const m = this.d.caChart;
                    const series = [{ name: 'CA période', data: m.current }];
                    if (this.compare) series.push({ name: 'Période préc.', data: m.previous });
                    return {
                        chart: { type: 'area', height: 300, fontFamily: 'var(--font-sans)', toolbar: { show: false }, animations: { easing: 'easeout', speed: 400 }, parentHeightOffset: 0 },
                        series: series, colors: ['#00453e', '#c5ccc9'],
                        stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 5] },
                        fill: { type: ['gradient', 'solid'], gradient: { shadeIntensity: 0.4, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] }, opacity: [1, 0] },
                        dataLabels: { enabled: false }, grid: { borderColor: '#eef1f0', strokeDashArray: 4, padding: { left: 6, right: 6 } },
                        xaxis: { categories: m.categories, labels: { style: { colors: '#76817d', fontSize: '11px' }, rotate: 0, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
                        yaxis: { labels: { style: { colors: '#76817d', fontSize: '11px' }, formatter: v => v >= 1000 ? (v / 1000).toFixed(0) + ' k€' : Math.round(v) + ' €' } },
                        legend: { show: false }, tooltip: { y: { formatter: v => this.eur(v) } },
                    };
                },

                donutOptions(labels, dataArr, colors) {
                    return {
                        chart: { type: 'donut', height: 190, fontFamily: 'var(--font-sans)' },
                        series: dataArr.length ? dataArr : [1], labels: labels.length ? labels : ['—'], colors: colors && colors.length ? colors : ['#e0e4e2'],
                        stroke: { width: 2, colors: ['#fff'] }, dataLabels: { enabled: false }, legend: { show: false },
                        plotOptions: { pie: { donut: { size: '68%', labels: { show: false } } } },
                        tooltip: { y: { formatter: v => this.fmt(v) } }, states: { hover: { filter: { type: 'darken', value: 0.9 } } },
                    };
                },

                barOptions(labels, dataArr) {
                    return {
                        chart: { type: 'bar', height: 290, fontFamily: 'var(--font-sans)', toolbar: { show: false } },
                        series: [{ name: 'Commandes', data: dataArr }], colors: ['#00453e'],
                        plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '62%' } },
                        dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, style: { colors: ['#3f4a46'], fontSize: '11px', fontWeight: 700 }, formatter: v => v },
                        xaxis: { categories: labels, labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
                        yaxis: { labels: { style: { colors: '#3f4a46', fontSize: '12px' } } }, grid: { show: false },
                        tooltip: { y: { formatter: v => v + ' commandes' } },
                    };
                },

                fmt(n) { return Math.round(n).toLocaleString('fr-FR'); },
                eur(n) { return Math.round(n).toLocaleString('fr-FR') + ' €'; },

                // -- helpers de teinte (badges/pastilles) --
                toneSoft(tone) {
                    const m = {
                        success: 'background:var(--success-100);color:var(--success-700)',
                        warning: 'background:var(--warning-100);color:var(--warning-700)',
                        danger: 'background:var(--danger-100);color:var(--danger-700)',
                        info: 'background:var(--info-100);color:var(--info-700)',
                        brand: 'background:var(--forest-50);color:var(--forest-700)',
                        accent: 'background:var(--lime-100);color:var(--lime-700)',
                        muted: 'background:var(--surface-sunken);color:var(--text-secondary)',
                    };
                    return m[tone] || m.muted;
                },
                toneText(tone) {
                    const m = {
                        success: 'var(--success-700)', warning: 'var(--warning-700)', danger: 'var(--danger-700)',
                        info: 'var(--info-700)', brand: 'var(--forest-600)', accent: 'var(--lime-700)', muted: 'var(--text-secondary)',
                    };
                    return m[tone] || 'var(--text-secondary)';
                },

                simBadge() {
                    return '<span title="Métrique non connectée à une source réelle (analytics / coût requis) — valeur simulée" '
                        + 'style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;letter-spacing:.02em;text-transform:uppercase;'
                        + 'padding:1px 7px;border-radius:9999px;background:var(--warning-100);color:var(--warning-700);white-space:nowrap">'
                        + '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>'
                        + 'stat à connecter</span>';
                },

                iconFor(key) {
                    const s = (p) => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
                    const s20 = (p) => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
                    const m = {
                        ca: s('<path d="M17 6.5A6 6 0 1 0 17 18M4 10h9M4 14h9"/>'),
                        orders: s('<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M3 4h2l2 12h11l2-8H6"/>'),
                        aov: s('<path d="M3 7h18v11H3z"/><path d="M3 11h18"/><circle cx="12" cy="14.5" r="1.6"/>'),
                        conv: s('<path d="M6 18 18 6"/><circle cx="7.5" cy="7.5" r="2"/><circle cx="16.5" cy="16.5" r="2"/>'),
                        'user-plus': s20('<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><path d="M18 8v5M20.5 10.5h-5"/>'),
                        file: s20('<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 13h6M9 17h4"/>'),
                        cart: s20('<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M3 4h2l2 12h11l2-8H6"/>'),
                        alert: s20('<path d="M12 3 2 20h20z"/><path d="M12 9v5M12 17h.01"/>'),
                        box: s('<path d="M12 3 3 7.5v9L12 21l9-4.5v-9z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>'),
                        tag: s('<path d="M6 18 18 6"/><circle cx="7.5" cy="7.5" r="2"/><circle cx="16.5" cy="16.5" r="2"/>'),
                        medal: s('<circle cx="12" cy="9" r="5"/><path d="m8.5 13-1.5 8 5-3 5 3-1.5-8"/>'),
                        chart: s('<path d="M4 19V5M4 19h16M8 15l3-4 3 3 4-6"/>'),
                    };
                    return m[key] || '';
                },
            };
        };
    </script>
</x-filament-panels::page>
