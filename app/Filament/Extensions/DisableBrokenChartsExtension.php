<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use Lunar\Admin\Filament\Widgets\Dashboard\Orders\AverageOrderValueChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\NewVsReturningCustomersChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrdersSalesChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrderTotalsChart;
use Lunar\Admin\Support\Extending\BaseExtension;

/**
 * Retire les widgets ApexChartWidget du dashboard Lunar qui crashent lors du
 * polling Livewire (RootTagMissing — ApexChart perd le panel context sur
 * /livewire/update). Bug upstream à reporter.
 */
class DisableBrokenChartsExtension extends BaseExtension
{
    public function getChartWidgets(array $widgets): array
    {
        $disabled = [
            OrdersSalesChart::class,
            OrderTotalsChart::class,
            AverageOrderValueChart::class,
            NewVsReturningCustomersChart::class,
        ];

        return array_values(array_diff($widgets, $disabled));
    }
}
