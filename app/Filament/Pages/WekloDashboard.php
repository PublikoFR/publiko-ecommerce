<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\Dashboard\DashboardStats;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Filament\Pages\Dashboard as LunarDashboard;

/**
 * Tableau de bord Weklo — remplace le dashboard Lunar (grille de widgets) par
 * la page design Weklo entièrement custom (Blade + Alpine + ApexCharts).
 *
 * Swappée dans le panel via AppServiceProvider::swapLunarPages() (réflexion sur
 * LunarPanelManager::$pages), en conservant le slug `dashboard` → la route
 * `filament.lunar.pages.dashboard` et l'URL `/admin` restent inchangées, donc
 * la navigation custom (Builder) continue de pointer dessus.
 *
 * Toutes les données de toutes les périodes sont pré-calculées côté serveur et
 * passées à Alpine : le changement de période / filtres se fait 100 % côté
 * client, ce qui évite le crash ApexCharts ↔ Livewire (RootTagMissing).
 */
class WekloDashboard extends LunarDashboard
{
    protected static ?string $slug = 'dashboard';

    protected static string $view = 'filament.pages.weklo-dashboard';

    protected static bool $shouldRegisterNavigation = false;

    /** Le design fournit son propre en-tête → on masque le heading Filament. */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /** Pas de widgets : la page est intégralement rendue par la vue custom. */
    public function getWidgets(): array
    {
        return [];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }

    /**
     * Payload complet (une entrée par période) sérialisé pour Alpine.
     * Si les paramètres GET ?start= et ?end= sont présents (format YYYY-MM-DD),
     * une clé 'custom' est ajoutée et Alpine la sélectionnera automatiquement.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDashboardData(): array
    {
        $stats = new DashboardStats;

        $data = collect(['jour', '7j', '30j', '12m'])
            ->mapWithKeys(fn (string $p) => [$p => $stats->build($p)])
            ->all();

        $start = request()->query('start');
        $end = request()->query('end');
        if (
            is_string($start) && is_string($end)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
            && $start <= $end
        ) {
            $data['custom'] = $stats->buildCustom($start, $end);
        }

        return $data;
    }
}
